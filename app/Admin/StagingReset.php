<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\{Audit,Auth,Config,Http,RedisStore,Store,Uuid,View};

/**
 * Bounded staging-only test reset. Intentionally never wipes the database.
 * Administrators, identities, production publications, invoices and credentials
 * remain intact. Full environment restore belongs in verified Cloudways backups.
 */
final class StagingReset
{
    public static function enabled(): bool
    {
        $env=strtolower(trim((string)Config::get('app.env','production')));
        $host=strtolower((string)($_SERVER['HTTP_HOST']??$_SERVER['SERVER_NAME']??''));
        $host=explode(':',$host)[0];
        return in_array($env,['staging','testing','development'],true)
            && Config::get('app.allow_staging_reset',false)===true
            && $host!=='' && !in_array($host,['platform.digiti.in','epaper.digiti.in','domains.digiti.in'],true);
    }

    private static function eligible(array $row): bool
    {
        $code=strtoupper((string)($row['organisation_code']??''));
        $host=strtolower((string)($row['hostname']??''));
        $base=strtolower((string)(parse_url((string)($row['base_url']??''),PHP_URL_HOST)?:''));
        return preg_match('/^(?:TEST_|DEMO_)[A-Z0-9_]+$/',$code)===1
            && $host!=='' && $host!==$base
            && in_array((string)$row['domain_type'],['CUSTOMER_CUSTOM','CUSTOMER_SUBDOMAIN'],true);
    }

    public static function run(string $method): never
    {
        Auth::requireAdmin();
        if(!self::enabled()) {
            if($method==='POST') throw new \RuntimeException('Reset is disabled outside an explicitly configured staging installation.');
            View::layout('Staging reset','<section class="card"><h2>Reset disabled</h2>'
                .'<p>Reset is available only on a separate staging hostname with app.env=staging '
                .'and app.allow_staging_reset=true in the private runtime configuration. '
                .'The production Platform is protected.</p></section>');
        }
        if($method==='POST') self::reset();
        $rows=Store::all('SELECT d.domain_guid,d.hostname,d.status,d.domain_type,a.base_url,o.organisation_code,
            o.display_name organisation_name,pt.display_name tenant_name
            FROM domains d JOIN organisations o ON o.organisation_guid=d.organisation_guid
            JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid
            JOIN applications a ON a.application_guid=d.application_guid
            ORDER BY d.updated_at DESC LIMIT 500');
        $items='';$n=0;
        foreach($rows as $r) {
            if(!self::eligible($r))continue;
            $n++;
            $guid=View::esc($r['domain_guid']);$host=View::esc($r['hostname']);
            $items.='<article class="stage-reset-row"><div><strong>'.$host.'</strong><small>'
                .View::esc($r['organisation_name'].' / '.$r['tenant_name'].' · '.$r['status'])
                .'</small></div><form method="post" class="stage-reset-form">'
                .'<input type="hidden" name="_csrf" value="'.View::esc(Http::csrf()).'">'
                .'<input type="hidden" name="domain_guid" value="'.$guid.'">'
                .'<label>Type <strong>'.$host.'</strong> to confirm'
                .'<input required name="confirm_hostname" autocomplete="off" placeholder="'.$host.'"></label>'
                .'<label class="stage-reset-confirm"><input type="checkbox" name="acknowledge" value="1" required>'
                .' I understand this restarts onboarding and temporarily disables the test domain</label>'
                .'<button type="submit" class="button danger" data-confirm="Reset test-domain onboarding status?">Reset test onboarding</button>'
                .'</form></article>';
        }
        $body='<section class="stage-reset-intro"><span class="home-eyebrow">STAGING ONLY</span>'
            .'<h2>Reset test onboarding without deleting customer data.</h2>'
            .'<p>Only domains belonging to organisations with codes starting TEST_ or DEMO_ are eligible. '
            .'A reset returns an eligible domain to AWAITING_DNS, clears prior DNS/TLS evidence and requires a new go-live review. '
            .'It does not delete the organisation, publisher tenant, editions, users, credentials or audit trail.</p>'
            .'</section><section class="card"><h2>Eligible test domains ('.$n.')</h2>'
            .($items?:'<p class="muted">No eligible staging test domains found. Keep live customer data out of test reset workflows.</p>')
            .'</section><p class="muted">To completely revert a staging database and media, restore a verified isolated Cloudways staging snapshot. '
            .'Do not perform an in-place destructive production reset.</p>';
        View::layout('Staging reset',$body,['subtitle'=>'Preview and reset one test domain at a time']);
    }

    private static function reset(): never
    {
        if(!self::enabled()) throw new \RuntimeException('Staging reset is disabled.');
        $guid=trim((string)($_POST['domain_guid']??''));
        if(!preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i',$guid))
            throw new \InvalidArgumentException('Choose a valid test domain.');
        $row=Store::one('SELECT d.domain_guid,d.hostname,d.status,d.domain_type,a.base_url,
            o.organisation_code FROM domains d JOIN organisations o ON o.organisation_guid=d.organisation_guid
            JOIN applications a ON a.application_guid=d.application_guid
            WHERE d.domain_guid=?',[Uuid::toBinary($guid)]);
        if(!$row||!self::eligible($row))throw new \RuntimeException('This domain is not an eligible staging test record.');
        $host=strtolower((string)$row['hostname']);
        if(strtolower(trim((string)($_POST['confirm_hostname']??'')))!==$host
            || ($_POST['acknowledge']??'')!=='1')
            throw new \InvalidArgumentException('Enter the exact test hostname and confirm the restart.');
        if($row['status']==='REMOVED')
            throw new \InvalidArgumentException('Restore the archived test domain before resetting its onboarding.');
        Store::tx(function()use($guid,$host):void{
            $n=Store::exec('UPDATE domains SET status="AWAITING_DNS",is_primary=0,
                verified_at=NULL,ssl_ready_at=NULL,activated_at=NULL,last_checked_at=NULL,
                dns_metadata=NULL,edge_metadata=NULL,updated_at=NOW(6)
                WHERE domain_guid=? AND status<>"REMOVED"',[Uuid::toBinary($guid)]);
            if($n!==1) throw new \RuntimeException('Test domain changed during reset. Reload and retry.');
            Audit::log('domain.staging_reset','domain',$guid,'SUCCESS',[
                'hostname'=>$host,'scope'=>'single_test_domain_onboarding',
                'preserved'=>['tenant','users','content','audit','credentials']
            ]);
        });
        RedisStore::del('domain:'.$host);
        View::flash('Staging test onboarding reset. This domain must pass DNS and go-live checks again.');
        Http::redirect('/admin/staging-reset');
    }
}
