<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\{Audit,Auth,Config,Http,RedisStore,Store,Uuid,View};

/**
 * Explicit recovery for a publisher hostname mistakenly registered as
 * the canonical host of a second application, but hosted on a shared app.
 * Never changes tenant GUID, organisation GUID, product GUID, or user access.
 */
final class SharedAppRecovery
{
    private static function e(mixed $v): string{return View::esc($v);}
    private static function bin(string $v): string{return Uuid::toBinary($v);}
    private static function host(string $url): string {
        $host=(string)(parse_url($url,PHP_URL_HOST)?:'');
        return strtolower(rtrim($host,'.'));
    }
    private static function superAdmin(): void
    {
        $admin=Auth::requireAdmin();
        $role=(string)Store::value(
            'SELECT role_key FROM platform_admins WHERE admin_guid=? AND status="ACTIVE"',
            [self::bin((string)$admin['admin_guid'])]
        );
        if($role!=='SUPER_ADMIN'){
            http_response_code(403);
            View::layout('Access restricted',
                '<section class="card"><p>A Platform SUPER_ADMIN is required for shared-application recovery.</p></section>');
        }
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
    }

    private static function record(string $guid,bool $lock=false): ?array
    {
        return Store::one('SELECT d.domain_guid,d.hostname,d.normalized_hostname,d.domain_type,d.status,'
            .'d.product_guid,d.application_guid,d.product_tenant_guid,d.organisation_guid,d.is_primary,'
            .'a.name application_name,a.base_url,a.application_key,a.status application_status,'
            .'p.name product_name,p.product_key,p.status product_status,'
            .'pt.display_name tenant_name,pt.status tenant_status,'
            .'o.display_name organisation_name,o.status organisation_status '
            .'FROM domains d '
            .'JOIN applications a ON a.application_guid=d.application_guid AND a.product_guid=d.product_guid '
            .'JOIN products p ON p.product_guid=d.product_guid '
            .'JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid '
            .'AND pt.organisation_guid=d.organisation_guid AND pt.product_guid=d.product_guid '
            .'JOIN organisations o ON o.organisation_guid=d.organisation_guid '
            .'WHERE d.domain_guid=? LIMIT 1'.($lock?' FOR UPDATE':''),[self::bin($guid)]);
    }

    private static function candidates(array $record): array
    {
        $applications=Store::all('SELECT application_guid,product_guid,name,application_key,base_url,status '
            .'FROM applications WHERE product_guid=? AND application_guid<>? AND status="ACTIVE" '
            .'ORDER BY name LIMIT 100',[
            self::bin((string)$record['product_guid']),
            self::bin((string)$record['application_guid'])
        ]);
        $candidates=[];
        foreach($applications as $application){
            $host=self::host((string)$application['base_url']);
            if($host==='')continue;
            $canonical=Store::one('SELECT domain_guid,hostname,domain_type,status,application_guid,product_guid '
                .'FROM domains WHERE normalized_hostname=? AND application_guid=? LIMIT 1',[
                $host,self::bin((string)$application['application_guid'])
            ]);
            if(self::eligible($record,$application,$canonical))$candidates[]=$application;
        }
        return $candidates;
    }

    /** Release contract; disallow cross-product or cross-tenant shortcuts. */
    public static function eligible(array $domain,array $target,?array $canonical): bool
    {
        $host=strtolower((string)($domain['hostname']??''));
        $appHost=self::host((string)($domain['base_url']??''));
        $destHost=self::host((string)($target['base_url']??''));
        // Recovery may target ONLY the configured shared installation; otherwise
        // a second canonical app could take over the real main ePaper hostname.
        $trustedSharedHost=self::host((string)Config::get('integrations.epaper_shared_base_url','https://epaper.digiti.in'));
        return $host!=='' && $appHost===$host
            && $trustedSharedHost!=='' && $destHost===$trustedSharedHost
            && $host!==$trustedSharedHost
            && $destHost!=='' && $destHost!==$host
            && in_array((string)($domain['domain_type']??''),['SAAS_DEFAULT','CUSTOMER_CUSTOM','CUSTOMER_SUBDOMAIN'],true)
            && (string)($domain['status']??'')!=='REMOVED'
            && (string)($domain['application_status']??'')==='ACTIVE'
            && (string)($domain['product_status']??'')==='ACTIVE'
            && (string)($domain['tenant_status']??'')==='ACTIVE'
            && (string)($domain['organisation_status']??'')==='ACTIVE'
            && (string)($target['status']??'')==='ACTIVE'
            && (string)($target['application_guid']??'')!==(string)($domain['application_guid']??'')
            && (string)($target['product_guid']??'')===(string)($domain['product_guid']??'')
            && is_array($canonical)
            && (string)($canonical['application_guid']??'')===(string)($target['application_guid']??'')
            && (string)($canonical['product_guid']??'')===(string)($target['product_guid']??'')
            && strtolower((string)($canonical['hostname']??''))===$destHost
            && (string)($canonical['domain_type']??'')==='SAAS_DEFAULT'
            && in_array((string)($canonical['status']??''),['ACTIVE','ROUTING_VALID','SSL_PROVISIONING'],true);
    }

    public static function run(string $guid,string $method): never
    {
        self::superAdmin();
        try{self::bin($guid);}catch(\Throwable){throw new \InvalidArgumentException('Invalid domain GUID.');}
        if($method==='POST')self::convert($guid);
        $d=self::record($guid);
        if(!$d)throw new \InvalidArgumentException('The publisher domain record could not be found.');
        $targets=self::candidates($d);
        $host=(string)$d['hostname'];
        $body='<section class="card"><h2>Existing domain — shared-application recovery</h2>'
            .'<p><strong>Hostname:</strong> '.self::e($host)
            .' · <strong>Publisher:</strong> '.self::e($d['organisation_name'].' / '.$d['tenant_name'])
            .' · <strong>Current application:</strong> '.self::e($d['application_name'])
            .' ('.self::e($d['base_url']).')</p>'
            .'<p>The current application Base URL equals the newspaper hostname. '
            .'If the newspaper actually runs on another registered Cloudways ePaper installation, '
            .'its application mapping is wrong. Do not change the Base URL of either application to hide the mismatch.</p>'
            .'<p>This operation retains the existing domain GUID, publisher organisation GUID, '
            .'publisher tenant GUID, product GUID, and all edition/media records. '
            .'It changes the domain to a customer alias of one <strong>existing application</strong>, '
            .'re-registers the OAuth callback, resets DNS/SSL approval and invalidates Platform domain cache.</p>';
        if(!$targets){
            $body.='<p><strong>No eligible shared application for this product.</strong> '
                .'Verify the configured main ePaper installation at '.self::e((string)Config::get('integrations.epaper_shared_base_url','https://epaper.digiti.in')).', its registered canonical hostname, and that the DCN tenant belongs '
                .'to that same product. Different products cannot be merged through this repair. '
                .'Do not change GUIDs directly in MariaDB or create duplicate publisher records.</p>'
                .'<p><a class="button" href="/admin/product-tenants">Inspect product tenants</a> '
                .'<a class="button" href="/admin/app-connections">Inspect application registrations</a></p>';
        }else{
            $options='<option value="">Select the actual shared Cloudways installation</option>';
            foreach($targets as $target)
                $options.='<option value="'.self::e($target['application_guid']).'">'
                    .self::e($target['name'].' · '.$target['base_url']).'</option>';
            $body.='<form method="post" action="/admin/domains/'.rawurlencode($guid).'/shared-application" class="form-grid">'
                .'<input type="hidden" name="_csrf" value="'.self::e(Http::csrf()).'">'
                .'<label>Target installed application<select name="target_application_guid" required>'
                .$options.'</select></label>'
                .'<label>Type existing hostname<input name="confirm_hostname" autocomplete="off" required placeholder="'.self::e($host).'"></label>'
                .'<label>Type shared application Base URL hostname<input name="confirm_app_hostname" autocomplete="off" required placeholder="epaper.digiti.in"></label>'
                .'<label><input type="checkbox" name="confirm_hosted" value="1" required> '
                .'I verified that this newspaper hostname routes to the selected shared Cloudways application.</label>'
                .'<label><input type="checkbox" name="confirm_isolation" value="1" required> '
                .'I verified the publisher tenant must remain unchanged and accept a fresh verification/go-live review.</label>'
                .'<div class="form-action"><button class="primary">Convert existing domain to shared app</button></div>'
                .'</form>';
        }
        $body.='<p><small>After conversion, the DNS/HTTPS and signed-in publisher checks must pass before go-live. '
            .'The redundant application registration is <strong>not</strong> deleted automatically; remove it only '
            .'after checking all dependencies. The ePaper cache may persist until its TTL expires.</small></p>'
            .'<a class="button" href="/admin/onboarding?domain='.rawurlencode($guid).'#domain-review">Return to publisher tracker</a></section>';
        View::layout('Shared ePaper application recovery',$body,[
            'subtitle'=>'Recover an existing publisher hostname without deleting its tenant or bypassing application identity',
        ]);
    }

    private static function convert(string $guid): never
    {
        if(($_POST['confirm_hosted']??'')!=='1'||($_POST['confirm_isolation']??'')!=='1')
            throw new \InvalidArgumentException('Confirm hosting, tenant preservation and verification requirements.');
        $selected=(string)($_POST['target_application_guid']??'');
        $bin=self::bin($selected);
        $typedHost=strtolower(trim((string)($_POST['confirm_hostname']??'')));
        $typedTarget=strtolower(trim((string)($_POST['confirm_app_hostname']??'')));
        $hostname='';
        Store::tx(static function()use($guid,$bin,$typedHost,$typedTarget,&$hostname):void {
            $domain=self::record($guid,true);
            if(!$domain)throw new \InvalidArgumentException('Domain no longer exists.');
            $host=strtolower((string)$domain['hostname']);
            if($typedHost!==$host)throw new \InvalidArgumentException('Type the exact existing publisher hostname.');
            $target=Store::one('SELECT application_guid,product_guid,name,application_key,base_url,status '
                .'FROM applications WHERE application_guid=? AND status="ACTIVE" LIMIT 1',[$bin]);
            if(!$target || $typedTarget!==self::host((string)$target['base_url']))
                throw new \InvalidArgumentException('Select and confirm the existing shared application Base URL hostname.');
            $anchor=Store::one('SELECT domain_guid,hostname,domain_type,status,application_guid,product_guid '
                .'FROM domains WHERE normalized_hostname=? AND application_guid=? LIMIT 1',[
                self::host((string)$target['base_url']),$bin
            ]);
            if(!self::eligible($domain,$target,$anchor))
                throw new \InvalidArgumentException('Cannot convert a system hostname or cross-product/invalid application registration.');
            $previous=(string)$domain['application_guid'];
            $updated=Store::exec('UPDATE domains SET application_guid=?,domain_type="CUSTOMER_CUSTOM",'
                .'status="AWAITING_DNS",verified_at=NULL,ssl_ready_at=NULL,activated_at=NULL,'
                .'dns_metadata=NULL,edge_metadata=NULL,last_checked_at=NULL,updated_at=NOW(6) '
                .'WHERE domain_guid=? AND application_guid=? AND product_tenant_guid=? AND product_guid=? '
                .'AND status<>"REMOVED"',[
                $bin,self::bin($guid),self::bin($previous),
                self::bin((string)$domain['product_tenant_guid']),
                self::bin((string)$domain['product_guid']),
            ]);
            if($updated!==1)throw new \RuntimeException('This domain was modified by another administrator. Reload and review.');
            $uri='https://'.$host.'/auth/callback';
            Store::exec('INSERT INTO application_redirect_uris(redirect_guid,application_guid,redirect_uri,status,created_at) '
                .'VALUES(?,?,?,"ACTIVE",NOW(6)) ON DUPLICATE KEY UPDATE status="ACTIVE"',[
                self::bin(Uuid::v7()),$bin,$uri
            ]);
            Store::exec('UPDATE application_redirect_uris SET status="REMOVED" '
                .'WHERE application_guid=? AND redirect_uri=?',[
                self::bin($previous),$uri
            ]);
            Audit::log('domain.shared_application_rebind','domain',$guid,'SUCCESS',[
                'hostname'=>$host,'old_application_guid'=>$previous,
                'new_application_guid'=>$target['application_guid'],
                'product_tenant_guid'=>$domain['product_tenant_guid'],
                'product_guid'=>$domain['product_guid'],
                'approval_reset'=>true,
            ]);
            $hostname=$host;
        });
        RedisStore::del('domain:'.$hostname);
        View::flash('Existing domain connected to the shared application. Publisher tenant and records were preserved. '
            .'Recheck DNS, HTTPS/Cloudways routing, publisher access and go-live acceptance.');
        Http::redirect('/admin/onboarding?domain='.rawurlencode($guid).'#domain-review');
    }
}
