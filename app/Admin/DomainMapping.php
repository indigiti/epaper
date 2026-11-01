<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\{Auth,Config,Http,RedisStore,Store,Uuid,View};

/**
 * Explicit, audited mapping change for EXISTING customer domains only.
 * Intentionally separate from general domain metadata editing: wrong app/tenant
 * associations can cause cross-publisher exposure and authentication failures.
 */
final class DomainMapping
{
    private static function e(mixed $value): string { return View::esc($value); }
    private static function bin(string $guid): string { return Uuid::toBinary($guid); }
    private static function isGuid(mixed $value): bool {
        return is_string($value) && preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i',$value)===1;
    }
    private static function requestedTenant(array $input): string {
        $quick=trim((string)($input['tenant_quickpick']??''));
        return $quick!==''?$quick:trim((string)($input['product_tenant_guid']??''));
    }
    private static function canonical(string $url): string {
        return strtolower((string)(parse_url($url,PHP_URL_HOST)?:''));
    }
    private static function mutable(array $domain): bool {
        if(!in_array($domain['domain_type'],['CUSTOMER_CUSTOM','CUSTOMER_SUBDOMAIN'],true)
            || $domain['status']==='REMOVED') return false;
        // This hostname is the application's primary installation, not a publisher alias.
        return self::canonical((string)$domain['base_url'])!==strtolower((string)$domain['hostname']);
    }
    private static function domain(string $guid): ?array
    {
        return Store::one('SELECT d.domain_guid,d.hostname,d.normalized_hostname,d.status,
            d.domain_type,d.product_guid,d.organisation_guid,d.product_tenant_guid,
            d.application_guid,d.is_primary,a.base_url,a.application_key,p.product_key
            FROM domains d JOIN applications a ON a.application_guid=d.application_guid
            JOIN products p ON p.product_guid=d.product_guid WHERE d.domain_guid=? LIMIT 1',[self::bin($guid)]);
    }

    /** Show compatible choices without exposing database identifiers to the operator. */
    public static function panel(array $r): string
    {
        $domain=self::domain((string)$r['domain_guid']);
        if(!$domain) return '';
        $currentTenant=self::e((string)$r['organisation'].' / '.(string)$r['tenant']);
        $currentApp=self::e((string)$r['application']);
        if(!self::mutable($domain)) {
            return '<section class="card"><h2>Publisher & application assignment</h2>'
                .'<p class="muted">This is a system or canonical application domain. Its publisher/application mapping is protected from changes here.</p>'
                .'<p><strong>Newspaper account:</strong> '.$currentTenant.' · <strong>Application:</strong> '.$currentApp.'</p>'
                .(strtolower((string)$domain['hostname'])===strtolower((string)(parse_url((string)Config::get('integrations.epaper_shared_base_url','https://epaper.digiti.in'),PHP_URL_HOST)?:''))
                    ?''
                    :'<p><a class="button" href="/admin/domains/'.rawurlencode((string)$domain['domain_guid']).'/shared-application">Check shared-app recovery</a></p>'
                        .'<p class="muted">A hostname wrongly registered as a second canonical application can be converted to an alias only after super-admin review and same-product checks.</p>')
                .'</section>';
        }
        $tenants=Store::all('SELECT pt.product_tenant_guid,pt.display_name tenant_name,o.display_name organisation_name
            FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid
            WHERE pt.product_guid=? AND pt.status="ACTIVE" AND o.status="ACTIVE"
            ORDER BY o.display_name,pt.display_name LIMIT 300',[self::bin($domain['product_guid'])]);
        $applications=Store::all('SELECT application_guid,name,application_key,base_url
            FROM applications WHERE product_guid=? AND status="ACTIVE"
            ORDER BY name LIMIT 100',[self::bin($domain['product_guid'])]);
        $tenantOptions='';
        $quickChoices='';
        $alternativeCount=0;
        $currentTenantListed=false;
        foreach($tenants as $tenant) {
            $isCurrent=$tenant['product_tenant_guid']===$domain['product_tenant_guid'];
            if($isCurrent)$currentTenantListed=true;
            $label=$tenant['organisation_name'].' / '.$tenant['tenant_name'];
            $tenantOptions.='<option value="'.self::e($tenant['product_tenant_guid']).'"'.($isCurrent?' selected':'').'>'
                .self::e($label).($isCurrent?' (current)':'').'</option>';
            if(!$isCurrent) {
                $alternativeCount++;
                // This radio is a server-supported alternative to the native dropdown.
                // No JavaScript is required; choosing it overrides the select value.
                $quickChoices.='<label class="domain-mapping-choice"><input type="radio" name="tenant_quickpick"'
                    .' value="'.self::e($tenant['product_tenant_guid']).'"><span><strong>'.self::e($label).'</strong>'
                    .'<small>Switch this website to this publisher account</small></span></label>';
            }
        }
        if(!$currentTenantListed)$tenantOptions='<option value="" selected disabled>Current newspaper unavailable — select a publisher</option>'.$tenantOptions;
        $quickChoices='<label class="domain-mapping-choice"><input type="radio" name="tenant_quickpick" value="" checked>'
            .'<span><strong>Use the dropdown selection</strong><small>No alternative selected</small></span></label>'.$quickChoices;
        $appOptions='';
        $currentApplicationListed=false;
        foreach($applications as $app) {
            $isCurrent=$app['application_guid']===$domain['application_guid'];
            if($isCurrent)$currentApplicationListed=true;
            $appOptions.='<option value="'.self::e($app['application_guid']).'"'.($isCurrent?' selected':'').'>'
                .self::e($app['name'].' · '.$app['base_url']).'</option>';
        }
        if(!$currentApplicationListed)$appOptions='<option value="" selected disabled>Current application unavailable — select a compatible application</option>'.$appOptions;
        $eligibility='<p class="domain-mapping-inventory">'.count($tenants).' eligible publisher account(s)'
            .' · '.count($applications).' compatible application(s)</p>';
        if($alternativeCount===0) {
            $eligibility.='<div class="domain-mapping-warning"><strong>No alternative publisher accounts available for this product.</strong> '
                .'This is why the account dropdown cannot switch. '
                .'Check the DCN tenant’s parent product and the statuses of the DCN organisation and tenant under '
                .'<a href="/admin/product-tenants">Product Tenants</a>. '
                .'A different product cannot be assigned here without a separate reviewed migration.</div>';
        }
        // Explain hidden DCN options based on actual Platform rows, rather than
        // allowing product-incompatible publisher assignments.
        if(strcasecmp((string)$domain['hostname'],'epaper.dcnnews.co.in')===0
            && !array_filter($tenants,static fn(array $t):bool=>
                stripos((string)$t['organisation_name'],'DCN')!==false
                || stripos((string)$t['tenant_name'],'DCNEP')!==false)) {
            $dcn=Store::all('SELECT pt.product_guid,pt.status tenant_status,o.status organisation_status,
                pt.display_name tenant_name,o.display_name organisation_name
                FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid
                WHERE UPPER(o.display_name) LIKE "DCN%" OR UPPER(pt.display_name) LIKE "DCNEP%"
                ORDER BY pt.created_at DESC LIMIT 20');
            if($dcn) {
                $reason=[];
                foreach($dcn as $candidate) {
                    $issues=[];
                    if($candidate['product_guid']!==$domain['product_guid']) $issues[]='different product';
                    if($candidate['tenant_status']!=='ACTIVE') $issues[]='tenant status '.$candidate['tenant_status'];
                    if($candidate['organisation_status']!=='ACTIVE') $issues[]='organisation status '.$candidate['organisation_status'];
                    if($issues) $reason[]=self::e($candidate['organisation_name'].' / '.$candidate['tenant_name'].' — '.implode(', ',$issues));
                }
                $eligibility.='<div class="domain-mapping-warning"><strong>Why DCN / DCNEP is missing:</strong> '
                    .($reason?implode('; ',$reason):'No eligible DCN option is returned for this domain product.')
                    .'. Correct the organisation/product relationship before changing the domain.</div>';
            } else {
                $eligibility.='<div class="domain-mapping-warning">DCN / DCNEP was not found in the current Platform records. '
                    .'Check Product Tenants and confirm it belongs to this Platform installation.</div>';
            }
        }
        $guid=rawurlencode((string)$domain['domain_guid']);
        $host=self::e($domain['hostname']);
        return '<section class="card domain-mapping-panel" id="publisher-application">'
            .'<div class="domain-mapping-heading"><div><h2>Connected publisher & application</h2>'
            .'<p class="muted">Change which newspaper this domain displays. Keep the application unchanged when moving DCN to its own publisher account.</p></div>'
            .'<a class="button" href="/admin/onboarding?domain='.$guid.'#domain-review">Open onboarding tracker</a></div>'
            .'<div class="domain-mapping-current"><span>Current publisher: <strong>'.$currentTenant.'</strong></span>'
            .'<span>Current application: <strong>'.$currentApp.'</strong></span></div>'
            .'<form method="post" action="/admin/domains/'.$guid.'/mapping" class="domain-mapping-form">'
            .'<input type="hidden" name="_csrf" value="'.self::e(Http::csrf()).'">'
            .$eligibility.'<div class="domain-mapping-fields">'
            .'<label>Newspaper account (tenant)<select name="product_tenant_guid" required>'.$tenantOptions.'</select>'
            .'<small>'.($domain['hostname']==='epaper.dcnnews.co.in'
                ?'For this website, select DCN / DCNEP to use DCN’s own editions.'
                :'Choose a separate publisher account when this website needs different editions.').'</small></label>'
            .'<label>Application<select name="application_guid" required>'.$appOptions.'</select>'
            .'<small>'.(count($applications)===1
                ?'Only one application is registered for this product. Leave this as EPAPER for DCN.'
                :'Normally leave this as the current ePaper application. Moving applications also requires matching DNS, SSL and sign-in settings.').'</small></label>'
            .'</div>'.($quickChoices!==''?'<div class="domain-mapping-quick"><h3>Can’t use the dropdown? Select a newspaper here instead</h3>'
                .'<p>Choose one option below to override the dropdown selection. Choose the shared installed application, not a separate application for each newspaper.</p>'
                .'<div class="domain-mapping-choice-list">'.$quickChoices.'</div></div>':'')
            .'<div class="domain-mapping-confirm">'
            .'<h3>Before changing this connection</h3><p>This keeps the domain record and does not move or copy newspaper editions. '
            .'The domain will need DNS verification and go-live approval again. Website access may be temporarily interrupted.</p>'
            .'<label class="domain-mapping-checkbox"><input type="checkbox" name="confirm_content" value="1" required>'
            .' I have reviewed the destination publisher and understand that content is not copied</label>'
            .'<label class="domain-mapping-checkbox"><input type="checkbox" name="confirm_restart" value="1" required>'
            .' I understand the domain must pass setup checks again before go-live</label>'
            .'<label>Type <strong>'.$host.'</strong> to confirm<input name="confirm_hostname"'
            .' required autocomplete="off" placeholder="'.$host.'"></label>'
            .'<button class="primary" type="submit">Update publisher & application</button></div></form></section>';
    }

    public static function save(string $guid,string $method): never
    {
        if($method!=='POST') Http::redirect('/admin/domains');
        Auth::requireAdmin();
        if(!self::isGuid($guid)) throw new \InvalidArgumentException('Invalid domain reference.');
        $d=self::domain($guid);
        if(!$d) throw new \InvalidArgumentException('Domain not found.');
        if(!self::mutable($d)) throw new \InvalidArgumentException('This application or system domain cannot be reassigned.');
        $tenantGuid=self::requestedTenant($_POST);
        $appGuid=trim((string)($_POST['application_guid']??''));
        $host=strtolower((string)$d['hostname']);
        $typed=strtolower(trim((string)($_POST['confirm_hostname']??'')));
        if(!self::isGuid($tenantGuid)||!self::isGuid($appGuid)) throw new \InvalidArgumentException('Select a valid publisher account and application.');
        if($typed!==$host) throw new \InvalidArgumentException('Type the exact domain name to confirm this change.');
        if(($_POST['confirm_content']??'')!=='1'||($_POST['confirm_restart']??'')!=='1')
            throw new \InvalidArgumentException('Review the two safety confirmations before continuing.');
        if($tenantGuid===$d['product_tenant_guid'] && $appGuid===$d['application_guid'])
            throw new \InvalidArgumentException('No changes selected. Choose a different publisher account or application.');
        $tenant=Store::one('SELECT pt.product_tenant_guid,pt.organisation_guid,pt.display_name,
            o.display_name organisation_name
            FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid
            WHERE pt.product_tenant_guid=? AND pt.product_guid=? AND pt.status="ACTIVE" AND o.status="ACTIVE" LIMIT 1',[
            self::bin($tenantGuid),self::bin($d['product_guid'])
        ]);
        $application=Store::one('SELECT application_guid,name,application_key,base_url
            FROM applications WHERE application_guid=? AND product_guid=? AND status="ACTIVE" LIMIT 1',[
            self::bin($appGuid),self::bin($d['product_guid'])
        ]);
        if(!$tenant||!$application)throw new \InvalidArgumentException('Choose an active publisher and compatible application for this product.');
        if(self::canonical((string)$application['base_url'])===$host)
            throw new \InvalidArgumentException('A canonical application hostname cannot be reassigned to a customer publisher.');
        // The application changing could require different hosting and identity integration.
        // Product compatibility alone does not configure a Cloudways domain or provision TLS.
        $previousTenant=(string)$d['product_tenant_guid'];
        $previousApplication=(string)$d['application_guid'];
        $admin=Auth::requireAdmin();
        Store::tx(function() use($guid,$d,$tenant,$application,$admin,$host,$previousTenant,$previousApplication): void {
            $updated=Store::exec('UPDATE domains SET organisation_guid=?,product_tenant_guid=?,
                application_guid=?,is_primary=0,status="AWAITING_DNS",verified_at=NULL,
                ssl_ready_at=NULL,activated_at=NULL,dns_metadata=NULL,edge_metadata=NULL,
                last_checked_at=NULL,updated_at=NOW(6)
                WHERE domain_guid=? AND product_tenant_guid=? AND application_guid=? AND status<>"REMOVED"',[
                self::bin($tenant['organisation_guid']),self::bin($tenant['product_tenant_guid']),
                self::bin($application['application_guid']),self::bin($guid),
                self::bin($previousTenant),self::bin($previousApplication)
            ]);
            if($updated!==1) throw new \RuntimeException('The domain changed during your edit. Reload the page and try again.');
            if($previousApplication!==$application['application_guid']){
                $uri='https://'.$host.'/auth/callback';
                Store::exec('INSERT INTO application_redirect_uris(redirect_guid,application_guid,redirect_uri,status,created_at)
                    VALUES(?,?,?,"ACTIVE",NOW(6)) ON DUPLICATE KEY UPDATE status="ACTIVE"',[
                    self::bin(Uuid::v7()),self::bin($application['application_guid']),$uri
                ]);
                Store::exec('UPDATE application_redirect_uris SET status="REMOVED"
                    WHERE application_guid=? AND redirect_uri=?',[
                    self::bin($previousApplication),$uri
                ]);
            }
            Store::exec('INSERT INTO audit_events(audit_event_guid,occurred_at,actor_type,actor_user_guid,
                action,resource_type,resource_guid,result,request_guid,ip_address,user_agent,metadata)
                VALUES(?,NOW(6),"PLATFORM_ADMIN",?,"domain.mapping.changed","domain",?,"SUCCESS",?,?,?,?)',[
                self::bin(Uuid::v7()),self::bin($admin['admin_guid']),self::bin($guid),
                self::bin(Http::requestGuid()),(string)($_SERVER['REMOTE_ADDR']??''),
                substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255),
                json_encode([
                    'hostname'=>$host,
                    'previous_tenant_guid'=>$previousTenant,
                    'new_tenant_guid'=>$tenant['product_tenant_guid'],
                    'previous_application_guid'=>$previousApplication,
                    'new_application_guid'=>$application['application_guid'],
                    'approval_reset'=>true
                ],JSON_UNESCAPED_SLASHES)
            ]);
        });
        RedisStore::del('domain:'.$host);
        View::flash('Domain linked to '.$tenant['organisation_name'].' / '.$tenant['display_name'].'. '
            .'Complete DNS verification, publisher access and final go-live checks. '
            .'Old content may remain cached briefly on the ePaper application.');
        Http::redirect('/admin/onboarding?domain='.rawurlencode($guid).'#domain-review');
    }
}
