<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\{Auth,Config,Database,DomainTools,Http,Store,Uuid,View};
use Digiti\Domain\EdgeProviderFactory;

/**
 * Human-reviewed publisher onboarding: no new schema or background services.
 * All progress comes from existing Platform records and the immutable go-live audit.
 */
final class Onboarding
{
    private static function e(mixed $value): string { return View::esc($value); }
    private static function csrf(): string { return '<input type="hidden" name="_csrf" value="'.self::e(Http::csrf()).'">'; }
    private static function gid(string $id): string { return Uuid::toBinary($id); }
    private static function url(string $guid): string { return '/admin/onboarding?domain='.rawurlencode($guid); }

    public static function index(string $method): never
    {
        if ($method === 'POST') self::create();
        $rows=self::records();
        $reviews=self::reviews();
        $selected=(string)($_GET['domain']??'');
        $filter=(string)($_GET['filter']??'all');
        $q=trim((string)($_GET['q']??''));
        if (!in_array($filter,['all','pending','review','live'],true)) $filter='all';
        if (strlen($q)>120) $q=substr($q,0,120);
        $users=Store::all('SELECT user_guid,display_name,email FROM platform_users WHERE status="ACTIVE" ORDER BY display_name ASC LIMIT 150');
        $tenants=Store::all('SELECT pt.product_tenant_guid,pt.display_name,o.display_name organisation_name,
            (SELECT COUNT(*) FROM domains d WHERE d.product_tenant_guid=pt.product_tenant_guid AND d.status<>"REMOVED") domain_count,
            (SELECT COUNT(*) FROM memberships m JOIN platform_users u ON u.user_guid=m.user_guid WHERE m.organisation_guid=pt.organisation_guid AND m.status="ACTIVE" AND u.status="ACTIVE" AND m.role_key IN ("OWNER","ADMIN","PUBLISHER_ADMIN")) owner_count,
            (SELECT s.service_access_state FROM subscriptions s WHERE s.product_tenant_guid=pt.product_tenant_guid ORDER BY s.created_at DESC LIMIT 1) service_access_state
            FROM product_tenants pt JOIN products p ON p.product_guid=pt.product_guid JOIN organisations o ON o.organisation_guid=pt.organisation_guid
            WHERE p.product_key="EPAPER" AND pt.status<>"REMOVED" AND o.status<>"REMOVED" ORDER BY o.display_name LIMIT 200');
        $detail='';
        if($selected!=='' && preg_match('/^[0-9a-f-]{36}$/i',$selected)) {
            $active=self::one($selected);
            if($active!==null) {
                $active['reviewed']=isset($reviews[$active['domain_guid']]);
                $detail=self::detail($active,$users);
            }
        }
        View::layout('Publisher Onboarding',OnboardingDashboard::render($rows,$reviews,$users,$tenants,$filter,$q,$detail),[
            'subtitle'=>'Registered domains, launch progress and clear next actions',
        ]);
    }

    public static function action(string $guid,string $type,string $method): never
    {
        if ($method!=='POST') Http::redirect(self::url($guid));
        if (!preg_match('/^[a-f0-9-]{36}$/i',$guid)) throw new \InvalidArgumentException('Invalid domain identifier.');
        $d=self::one($guid);
        if (!$d) throw new \RuntimeException('Publisher domain not found.');
        if ($type==='verify') self::verify($d);
        if ($type==='owner') self::owner($d);
        if ($type==='confirm') self::confirm($d);
        if ($type==='reassign') self::reassign($d);
        throw new \RuntimeException('Unsupported onboarding action.');
    }

    /**
     * Reassign an EXISTING customer hostname to a different EPAPER tenant.
     * Deliberately resets domain readiness; infrastructure domains cannot move.
     */
    private static function reassign(array $domain): never
    {
        $host=(string)$domain['hostname'];
        $canonical=(string)(parse_url((string)($domain['application_url']??''),PHP_URL_HOST)?:'');
        if(($canonical!==''&&strcasecmp($canonical,$host)===0)
            || in_array(strtoupper((string)($domain['domain_type']??'')),['PLATFORM','PLATFORM_AUTH','SAAS_DEFAULT'],true)) {
            throw new \RuntimeException('The canonical ePaper application hostname cannot be reassigned.');
        }
        $targetGuid=trim((string)($_POST['target_tenant_guid']??''));
        $typed=trim(strtolower((string)($_POST['confirm_hostname']??'')));
        if(!preg_match('/^[a-f0-9-]{36}$/i',$targetGuid)) throw new \InvalidArgumentException('Choose a valid destination EPAPER tenant.');
        if($typed!==strtolower($host)) throw new \InvalidArgumentException('Enter the exact hostname to confirm reassignment.');
        if(($_POST['confirm_tenant_reset']??'')!=='1') throw new \InvalidArgumentException('Acknowledge that publisher activation must be repeated.');
        if(($_POST['confirm_content']??'')!=='1') throw new \InvalidArgumentException('Confirm the destination publisher content and data have been reviewed.');
        if($targetGuid===$domain['product_tenant_guid']) throw new \InvalidArgumentException('The selected tenant already owns this domain.');
        $target=Store::one('SELECT pt.product_tenant_guid,pt.organisation_guid,pt.display_name,o.display_name organisation_name
            FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid
            JOIN products p ON p.product_guid=pt.product_guid
            WHERE pt.product_tenant_guid=? AND pt.product_guid=? AND p.product_key="EPAPER"
                AND pt.status="ACTIVE" AND o.status="ACTIVE" LIMIT 1',[
            self::gid($targetGuid),self::gid($domain['product_guid'])
        ]);
        if(!$target) throw new \InvalidArgumentException('Select an active EPAPER tenant. Create the target organisation and tenant first.');
        $admin=Auth::requireAdmin();
        Store::tx(function()use($domain,$target,$admin,$host):void{
            $affected=Store::exec('UPDATE domains SET organisation_guid=?,product_tenant_guid=?,is_primary=0,
                status="AWAITING_DNS",verified_at=NULL,ssl_ready_at=NULL,activated_at=NULL,
                dns_metadata=NULL,last_checked_at=NULL,updated_at=NOW(6)
                WHERE domain_guid=? AND product_tenant_guid=? AND status<>"REMOVED"',[
                self::gid($target['organisation_guid']),self::gid($target['product_tenant_guid']),
                self::gid($domain['domain_guid']),self::gid($domain['product_tenant_guid'])
            ]);
            if($affected!==1) throw new \RuntimeException('Domain changed concurrently. Reload and retry.');
            Store::exec('INSERT INTO audit_events(audit_event_guid,occurred_at,actor_type,actor_user_guid,action,resource_type,resource_guid,result,request_guid,ip_address,user_agent,metadata) VALUES(?,NOW(6),"PLATFORM_ADMIN",?,"domain.onboarding.reassign","domain",?,"SUCCESS",?,?,?,?)',[
                self::gid(Uuid::v7()),self::gid($admin['admin_guid']),self::gid($domain['domain_guid']),self::gid(Http::requestGuid()),
                (string)($_SERVER['REMOTE_ADDR']??''),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255),
                json_encode([
                    'hostname'=>$host,
                    'old_tenant_guid'=>$domain['product_tenant_guid'],
                    'new_tenant_guid'=>$target['product_tenant_guid'],
                    'new_organisation_guid'=>$target['organisation_guid'],
                    'dns_and_go_live_reset'=>true
                ],JSON_UNESCAPED_SLASHES)
            ]);
        });
        \Digiti\Core\RedisStore::del('domain:'.$host);
        View::flash('Domain moved to '.$target['organisation_name'].' / '.$target['display_name'].'. Verify DNS again, confirm publisher access and subscription, then approve go-live. Clear ePaper domain caches before testing.');
        Http::redirect(self::url($domain['domain_guid']));
    }

    private static function create(): never
    {
        $type=(string)($_POST['mode']??'new');
        if (!in_array($type,['new','existing'],true)) throw new \InvalidArgumentException('Choose a valid onboarding mode.');
        $host=DomainTools::normalize((string)($_POST['hostname']??''));
        if (!str_contains($host,'.')||filter_var($host,FILTER_VALIDATE_IP)||str_ends_with($host,'.local')) throw new \InvalidArgumentException('A valid public publisher hostname is required.');
        if (Store::value('SELECT 1 FROM domains WHERE normalized_hostname=? LIMIT 1',[$host])) throw new \InvalidArgumentException('This domain already exists in Platform. Open its onboarding record.');
        $app=Store::one('SELECT a.application_guid,p.product_guid FROM applications a JOIN products p ON p.product_guid=a.product_guid WHERE p.product_key="EPAPER" AND a.status="ACTIVE" AND p.status="ACTIVE" ORDER BY (a.application_key="city_epaper") DESC LIMIT 1');
        if (!$app) throw new \RuntimeException('Register an active EPAPER application before adding publisher domains.');
        $owner=(string)($_POST['owner_user_guid']??'');
        if ($owner!==''&&!Store::value('SELECT 1 FROM platform_users WHERE user_guid=? AND status="ACTIVE"',[self::gid($owner)])) throw new \InvalidArgumentException('The selected owner is not an active Platform user.');
        $newGuid=Store::tx(function() use($type,$host,$app,$owner):string {
            if($type==='new') {
                $name=trim((string)($_POST['organisation_name']??''));
                $customCode=trim((string)($_POST['organisation_code']??''));
                $code=trim((string)preg_replace('/[^A-Z0-9_]+/','_',strtoupper($customCode!==''?$customCode:$name)),'_');
                if($name===''||strlen($name)>190||$code===''||strlen($code)>64) throw new \InvalidArgumentException('Enter a publisher name (up to 190 characters) and a valid short code.');
                if(Store::value('SELECT 1 FROM organisations WHERE organisation_code=? LIMIT 1',[$code]))
                    throw new \InvalidArgumentException('This publisher code already exists. Select an existing publisher under Additional domain, or enter a different code in Advanced options.');
                $orgGuid=Uuid::v7();
                Store::exec('INSERT INTO organisations(organisation_guid,organisation_code,legal_name,display_name,status,created_at,updated_at) VALUES(?,?,?,?,"ACTIVE",NOW(6),NOW(6))',[self::gid($orgGuid),$code,$name,$name]);
                $tenantGuid=Uuid::v7();
                Store::exec('INSERT INTO product_tenants(product_tenant_guid,organisation_guid,product_guid,display_name,status,created_at,updated_at) VALUES(?,?,?,?,"ACTIVE",NOW(6),NOW(6))',[self::gid($tenantGuid),self::gid($orgGuid),self::gid($app['product_guid']),$name]);
            } else {
                $tenantGuid=(string)($_POST['product_tenant_guid']??'');
                $row=Store::one('SELECT pt.organisation_guid FROM product_tenants pt JOIN products p ON p.product_guid=pt.product_guid JOIN organisations o ON o.organisation_guid=pt.organisation_guid WHERE pt.product_tenant_guid=? AND p.product_key="EPAPER" AND pt.status="ACTIVE" AND o.status="ACTIVE"',[self::gid($tenantGuid)]);
                if (!$row) throw new \InvalidArgumentException('Choose an active EPAPER tenant.');
                $orgGuid=(string)$row['organisation_guid'];
            }
            $dGuid=Uuid::v7();
            $token='digiti_'.bin2hex(random_bytes(16));
            Store::exec('INSERT INTO domains(domain_guid,organisation_guid,product_guid,application_guid,product_tenant_guid,hostname,normalized_hostname,domain_type,status,is_primary,verification_token,created_at,updated_at) VALUES(?,?,?,?,?,?,?,"CUSTOMER_CUSTOM","AWAITING_DNS",0,?,NOW(6),NOW(6))',[
                self::gid($dGuid),self::gid($orgGuid),self::gid($app['product_guid']),self::gid($app['application_guid']),$host,$host,$token
            ]);
            Store::exec('INSERT INTO application_redirect_uris(redirect_guid,application_guid,redirect_uri,status,created_at) VALUES(?,?,?,"ACTIVE",NOW(6)) ON DUPLICATE KEY UPDATE status="ACTIVE"',[
                self::gid(Uuid::v7()),self::gid($app['application_guid']),'https://'.$host.'/auth/callback'
            ]);
            if($owner!==''){
                $membership=Store::one('SELECT membership_guid,role_key,status FROM memberships WHERE organisation_guid=? AND user_guid=?',[self::gid($orgGuid),self::gid($owner)]);
                if (!$membership) Store::exec('INSERT INTO memberships(membership_guid,organisation_guid,user_guid,role_key,status,created_at,updated_at) VALUES(?,?,?,"OWNER","ACTIVE",NOW(6),NOW(6))',[self::gid(Uuid::v7()),self::gid($orgGuid),self::gid($owner)]);
                elseif ($membership['status']!=='ACTIVE'||!in_array($membership['role_key'],['OWNER','ADMIN','PUBLISHER_ADMIN'],true)) throw new \InvalidArgumentException('This owner has an incompatible organisation membership; edit it before onboarding.');
            }
            return $dGuid;
        });
        \Digiti\Core\Audit::log('domain.onboarding.created','domain',$newGuid,'SUCCESS',['mode'=>$type,'hostname'=>$host]);
        View::flash('Publisher domain registered. Continue with user access, subscription and DNS verification.');
        Http::redirect(self::url($newGuid));
    }

    private static function owner(array $d): never
    {
        $user=(string)($_POST['owner_user_guid']??'');
        if (!preg_match('/^[a-f0-9-]{36}$/i',$user)||!Store::value('SELECT 1 FROM platform_users WHERE user_guid=? AND status="ACTIVE"',[self::gid($user)])) throw new \InvalidArgumentException('Choose an active Platform user.');
        $existing=Store::one('SELECT membership_guid,role_key,status FROM memberships WHERE organisation_guid=? AND user_guid=?',[self::gid($d['organisation_guid']),self::gid($user)]);
        if ($existing && ($existing['status']!=='ACTIVE'||!in_array($existing['role_key'],['OWNER','ADMIN','PUBLISHER_ADMIN'],true))) throw new \InvalidArgumentException('This user has an incompatible membership; edit it in User Management.');
        if (!$existing) Store::exec('INSERT INTO memberships(membership_guid,organisation_guid,user_guid,role_key,status,created_at,updated_at) VALUES(?,?,?,"OWNER","ACTIVE",NOW(6),NOW(6))',[self::gid(Uuid::v7()),self::gid($d['organisation_guid']),self::gid($user)]);
        \Digiti\Core\Audit::log('domain.onboarding.owner_assigned','domain',$d['domain_guid']);
        View::flash('Publisher owner access is assigned.');
        Http::redirect(self::url($d['domain_guid']));
    }

    private static function verify(array $d): never
    {
        $dns=DomainTools::verifyDns($d['hostname'],$d['verification_token']);
        $status='AWAITING_DNS';$edge=[];
        if ($dns['verified']) {
            try {
                $edge=EdgeProviderFactory::make()->provision($d['hostname'],json_decode((string)($d['edge_metadata']??'{}'),true)?:[]);
                $status=(string)($edge['status']??'ROUTING_VALID');
            } catch (\Throwable $e) {
                $status='ROUTING_VALID';
                $edge=['provider_error'=>$e->getMessage()];
            }
        }
        Store::exec('UPDATE domains SET status=?,dns_metadata=?,edge_metadata=?,verified_at=IF(?<> "AWAITING_DNS",COALESCE(verified_at,NOW(6)),NULL),last_checked_at=NOW(6),updated_at=NOW(6) WHERE domain_guid=?',[
            $status,json_encode($dns),json_encode($edge),$status,self::gid($d['domain_guid'])
        ]);
        \Digiti\Core\RedisStore::del('domain:'.$d['hostname']);
        \Digiti\Core\Audit::log('domain.onboarding.dns_check','domain',$d['domain_guid'],$dns['verified']?'SUCCESS':'FAILED',['hostname'=>$d['hostname'],'status'=>$status]);
        View::flash($dns['verified']?'DNS ownership confirmed. Verify actual hosting and HTTPS before go-live.':'DNS has not passed verification. Check the TXT/CNAME records below.');
        Http::redirect(self::url($d['domain_guid']));
    }

    private static function confirm(array $d): never
    {
        $fresh=self::one($d['domain_guid']);
        if (!$fresh) throw new \RuntimeException('Domain no longer exists.');
        if (!in_array((string)$fresh['status'],['ROUTING_VALID','SSL_PROVISIONING','ACTIVE'],true)||empty($fresh['verified_at'])) throw new \RuntimeException('DNS must be verified before go-live approval.');
        if ((int)$fresh['owner_count']===0) throw new \RuntimeException('Assign an active publisher OWNER, ADMIN or PUBLISHER_ADMIN before go-live.');
        if ((string)($fresh['service_access_state']??'')!=='FULL') throw new \RuntimeException('Activate a subscription with FULL service access before go-live.');
        $edge=json_decode((string)($fresh['edge_metadata']??''),true)?:[];
        if (($edge['provider']??'')==='cloudflare' && !(($edge['edge_status']??'')==='active' && in_array(($edge['ssl_status']??''),['active','deployed'],true))) throw new \RuntimeException('Cloudflare custom hostname and TLS are not active yet.');
        $host=trim(strtolower((string)($_POST['confirm_hostname']??'')));
        if ($host!==strtolower((string)$fresh['hostname'])) throw new \InvalidArgumentException('Type the exact publisher hostname to confirm activation.');
        foreach (['confirm_ssl','confirm_origin','confirm_login','confirm_isolation'] as $field) {
            if (($_POST[$field]??'')!=='1') throw new \InvalidArgumentException('Confirm all four real-world deployment checks first.');
        }
        $admin=Auth::admin();
        if (!$admin) throw new \RuntimeException('Platform administrator login required.');
        Store::tx(function() use($fresh,$admin):void {
            Store::exec('UPDATE domains SET status="ACTIVE",ssl_ready_at=NOW(6),activated_at=COALESCE(activated_at,NOW(6)),updated_at=NOW(6) WHERE domain_guid=?',[self::gid($fresh['domain_guid'])]);
            Store::exec('INSERT INTO audit_events(audit_event_guid,occurred_at,actor_type,actor_user_guid,action,resource_type,resource_guid,result,request_guid,ip_address,user_agent,metadata) VALUES(?,NOW(6),"PLATFORM_ADMIN",?,"domain.onboarding.confirm","domain",?,"SUCCESS",?,?,?,?)',[
                self::gid(Uuid::v7()),self::gid($admin['admin_guid']),self::gid($fresh['domain_guid']),self::gid(Http::requestGuid()),
                (string)($_SERVER['REMOTE_ADDR']??''),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255),
                json_encode(['hostname'=>$fresh['hostname'],'method'=>'operator_attestation','checks'=>['https_certificate','cloudways_origin','publisher_login','tenant_isolation']],JSON_UNESCAPED_SLASHES)
            ]);
        });
        \Digiti\Core\RedisStore::del('domain:'.$fresh['hostname']);
        View::flash('Go-live approved and audit-recorded. The domain is marked ACTIVE; monitor live traffic and customer acceptance.');
        Http::redirect(self::url($fresh['domain_guid']));
    }

    private static function records(?string $domainGuid=null): array
    {
        $where=$domainGuid===null?'':' AND d.domain_guid=?';
        return Store::all('SELECT d.domain_guid,d.organisation_guid,d.product_guid,d.domain_type,d.is_primary,d.product_tenant_guid,d.hostname,d.status,d.verification_token,d.dns_metadata,d.edge_metadata,d.verified_at,d.ssl_ready_at,d.activated_at,d.last_checked_at,d.created_at,d.updated_at,
            o.display_name organisation_name,o.status organisation_status,pt.display_name tenant_name,pt.status tenant_status,
            a.name application_name,a.status application_status,a.base_url application_url,
            (SELECT COUNT(*) FROM memberships m JOIN platform_users u ON u.user_guid=m.user_guid WHERE m.organisation_guid=d.organisation_guid AND m.status="ACTIVE" AND u.status="ACTIVE" AND m.role_key IN ("OWNER","ADMIN","PUBLISHER_ADMIN")) owner_count,
            (SELECT s.service_access_state FROM subscriptions s WHERE s.product_tenant_guid=d.product_tenant_guid ORDER BY s.created_at DESC LIMIT 1) service_access_state
            FROM domains d
            JOIN products p ON p.product_guid=d.product_guid AND p.product_key="EPAPER"
            JOIN organisations o ON o.organisation_guid=d.organisation_guid
            JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid
            JOIN applications a ON a.application_guid=d.application_guid
            WHERE d.status<>"REMOVED"'.$where.'
            ORDER BY d.updated_at DESC LIMIT '.($domainGuid===null?200:1),$domainGuid===null?[]:[self::gid($domainGuid)]);
    }

    private static function one(string $guid): ?array
    {
        foreach(self::records($guid) as $r) if($r['domain_guid']===$guid) return $r;
        return null;
    }

    private static function reviews(): array
    {
        $out=[];
        foreach(Store::all('SELECT DISTINCT approved.resource_guid FROM audit_events approved
            WHERE approved.resource_type="domain" AND approved.action="domain.onboarding.confirm"
                AND approved.result="SUCCESS"
                AND NOT EXISTS (
                    SELECT 1 FROM audit_events changed
                    WHERE changed.resource_type="domain" AND changed.resource_guid=approved.resource_guid
                        AND changed.occurred_at>=approved.occurred_at
                        AND changed.action IN (
                            "domain.mapping.changed","domain.onboarding.reassign",
                            "domain.staging_reset","domain.remove","registry.restore"
                        )
                )') as $r) $out[$r['resource_guid']]=true;
        return $out;
    }

    public static function state(array $d): array
    {
        $reviewed=(bool)($d['reviewed']??false);
        $tenant=$d['organisation_status']==='ACTIVE'&&$d['tenant_status']==='ACTIVE'&&$d['application_status']==='ACTIVE';
        $owner=(int)$d['owner_count']>0;
        $plan=($d['service_access_state']??'')==='FULL';
        $dns=!empty($d['verified_at'])&&in_array($d['status'],['ROUTING_VALID','SSL_PROVISIONING','ACTIVE'],true);
        $edge=json_decode((string)($d['edge_metadata']??''),true)?:[];
        $tls=$reviewed || (($edge['provider']??'')==='cloudflare' && ($edge['edge_status']??'')==='active' && in_array(($edge['ssl_status']??''),['active','deployed'],true));
        $live=$reviewed && $d['status']==='ACTIVE' && $tenant && $owner && $plan && $dns;
        $steps=[
            ['name'=>'Publisher','done'=>$tenant,'pending'=>'Tenant or application inactive','url'=>'/admin/product-tenants'],
            ['name'=>'Owner access','done'=>$owner,'pending'=>'Assign publisher owner','url'=>self::url($d['domain_guid'])],
            ['name'=>'Subscription','done'=>$plan,'pending'=>'Enable FULL service access','url'=>'/admin/subscriptions'],
            ['name'=>'DNS','done'=>$dns,'pending'=>'Verify domain ownership','url'=>self::url($d['domain_guid'])],
            ['name'=>'HTTPS / routing','done'=>$tls,'pending'=>'Confirm TLS and origin routing','url'=>self::url($d['domain_guid'])],
            ['name'=>'Go-live','done'=>$live,'pending'=>'Final operator confirmation','url'=>self::url($d['domain_guid'])]
        ];
        $block='';$link=self::url($d['domain_guid']);
        if (in_array($d['status'],['REVALIDATION_FAILED','SUSPENDED'],true)) { $block='Domain suspended or requires revalidation'; }
        else foreach($steps as $step) if(!$step['done']) { $block=$step['pending'];$link=$step['url'];break; }
        $age=strtotime((string)$d['updated_at']);$stalled=!$live&&$d['status']!=='ACTIVE'&&$age!==false&&$age<time()-48*3600;
        return ['steps'=>$steps,'done'=>count(array_filter($steps,fn($s)=>$s['done'])),'live'=>$live,'stalled'=>$stalled,'blocker'=>$block?:'Ready','link'=>$link];
    }

    private static function detail(array $d,array $users): string
    {
        $guid=$d['domain_guid'];$d['reviewed']=isset(self::reviews()[$guid]);$state=self::state($d);
        $hostname=(string)$d['hostname'];
        $canonicalHost=(string)(parse_url((string)($d['application_url']??''),PHP_URL_HOST)?:'');
        $sharesMain=false;
        if($canonicalHost!=='' && strcasecmp($canonicalHost,$hostname)!==0) {
            $sharesMain=(bool)Store::value('SELECT 1 FROM domains
                WHERE normalized_hostname=? AND product_tenant_guid=? AND status<>"REMOVED" LIMIT 1',[
                strtolower($canonicalHost),self::gid($d['product_tenant_guid'])
            ]);
        }
        $sharedNotice=$sharesMain
            ?'<div class="ob-tenant-warning"><strong>This website shares the main ePaper newspaper.</strong>'
                .'<p>The domain is connected, but both websites use the same publisher tenant and will show the same editions.'
                .' If this is not intentional, choose a separate DCN/Pune Mirror publisher below.</p>'
                .'<a class="button" href="#change-publisher-tenant">Change publisher tenant</a></div>'
            :'';

        $steps='';foreach($state['steps'] as $i=>$step) {
            $done=$step['done'];$steps.='<li class="'.($done?'complete':'pending').'"><span class="ob-step-mark">'.($done?'&#10003;':($i+1)).'</span><div><strong>'.self::e($step['name']).'</strong><small>'.self::e($done?'Completed':$step['pending']).'</small></div></li>';
        }
        $target=(string)Config::get('app.domains_target','domains.digiti.in');
        $txt='_digiti-verification.'.$d['hostname'];
        $dns='<div class="ob-records"><div><span>CNAME</span><code>'.self::e($d['hostname']).'</code><code>'.self::e($target).'</code></div><div><span>Alternative TXT verification</span><code>'.self::e($txt).'</code><code>'.self::e($d['verification_token']).'</code></div></div>';
        $userOptions='<option value="">Choose active Platform user</option>';foreach($users as $u) $userOptions.='<option value="'.self::e($u['user_guid']).'">'.self::e($u['display_name'].' · '.$u['email']).'</option>';
        $verify='<form method="post" action="/admin/onboarding/'.$guid.'/verify">'.self::csrf().'<button type="submit">Check DNS again</button></form>';
        $assign='<form class="ob-owner-form" method="post" action="/admin/onboarding/'.$guid.'/owner">'.self::csrf().'<label>Publisher owner<select name="owner_user_guid" required>'.$userOptions.'</select></label><button type="submit">Assign owner</button></form>';
        $canApprove=$state['steps'][0]['done']&&$state['steps'][1]['done']&&$state['steps'][2]['done']&&$state['steps'][3]['done'] && !in_array($d['status'],['SUSPENDED','REVALIDATION_FAILED'],true);
        $confirm='<form class="ob-confirm-form" method="post" action="/admin/onboarding/'.$guid.'/confirm">'.self::csrf().'<h3>Operator go-live confirmation</h3><p class="muted">These are real-world acceptance checks, not automated probe results. Confirm only after visiting the publisher hostname and signing in as its authorised publisher.</p>'
            .'<label class="ob-check"><input type="checkbox" name="confirm_ssl" value="1" required> HTTPS certificate is valid for this hostname</label>'
            .'<label class="ob-check"><input type="checkbox" name="confirm_origin" value="1" required> DNS and Cloudways/edge route to the shared ePaper application</label>'
            .'<label class="ob-check"><input type="checkbox" name="confirm_login" value="1" required> Publisher can sign in and access the correct dashboard</label>'
            .'<label class="ob-check"><input type="checkbox" name="confirm_isolation" value="1" required> This domain cannot view another publisher’s private editions or media</label>'
            .'<label>Type the exact hostname to approve<input name="confirm_hostname" required autocomplete="off" placeholder="'.self::e($d['hostname']).'"></label>'
            .'<button class="primary" type="submit"'.($canApprove?'':' disabled').'>'.($state['live']?'Reconfirm live domain':'Confirm go-live').'</button>'
            .(!$canApprove?'<p class="ob-warning">Complete publisher access, subscription and DNS verification before confirming.</p>':'')
            .'</form>';
        $reassign='';
        $canonical=(string)(parse_url((string)($d['application_url']??''),PHP_URL_HOST)?:'');
        $protected=($canonical!==''&&strcasecmp($canonical,(string)$d['hostname'])===0)
            || in_array(strtoupper((string)($d['domain_type']??'')),['PLATFORM','PLATFORM_AUTH','SAAS_DEFAULT'],true);
        if(!$protected) {
            $destinations=Store::all('SELECT pt.product_tenant_guid,pt.display_name,o.display_name organisation_name
                FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid
                JOIN products p ON p.product_guid=pt.product_guid
                WHERE p.product_key="EPAPER" AND pt.status="ACTIVE" AND o.status="ACTIVE"
                AND pt.product_tenant_guid<>? ORDER BY o.display_name,pt.display_name LIMIT 200',[
                self::gid($d['product_tenant_guid'])
            ]);
            $options='<option value="">Select a separate publisher tenant</option>';
            foreach($destinations as $t) $options.='<option value="'.self::e($t['product_tenant_guid']).'">'.self::e($t['organisation_name'].' / '.$t['display_name']).'</option>';
            $reassign='<details class="ob-reassign" id="change-publisher-tenant"><summary>Wrong newspaper showing? Change publisher tenant</summary>'
                .'<div class="ob-reassign-body"><p><strong>Current publisher:</strong> '.self::e($d['organisation_name'].' / '.$d['tenant_name']).'</p>'
                .'<p>This operation changes the EXISTING domain record; it does not reinstall ePaper or copy editions. '
                .'Create a separate organisation and EPAPER product tenant first if this publication needs its own content. '
                .'The hostname will be placed in AWAITING_DNS and remain unavailable until verification and onboarding are completed. '
                .'Existing ePaper caches may temporarily continue serving old content until their TTL expires or an administrator clears them.</p>'
                .'<div class="actions"><a class="button small" href="/admin/organisations">Organisations</a>'
                .'<a class="button small" href="/admin/product-tenants">Product tenants</a></div>'
                .'<form method="post" action="/admin/onboarding/'.$guid.'/reassign" class="ob-reassign-form">'.self::csrf()
                .'<label>New publisher tenant<select name="target_tenant_guid" required>'.$options.'</select></label>'
                .'<label class="ob-check"><input type="checkbox" name="confirm_content" value="1" required> '
                .'I reviewed the destination tenant and understand that newspaper content is not transferred automatically</label>'
                .'<label class="ob-check"><input type="checkbox" name="confirm_tenant_reset" value="1" required> '
                .'I accept re-verification, temporary downtime and the need to clear ePaper caches</label>'
                .'<label>Type '.self::e($d['hostname']).' to confirm<input name="confirm_hostname" autocomplete="off" required></label>'
                .'<button class="danger" type="submit"'.(!$destinations?' disabled':'').'>Reassign existing domain</button>'
                .'</form></div></details>';
        }
        return '<section id="domain-review" class="card ob-detail" aria-label="Publisher onboarding tracker"><div class="ob-section-head"><div><span class="ob-eyebrow">DOMAIN TRACKER</span><h2>'.self::e($d['hostname']).'</h2><p class="muted">'.self::e($d['organisation_name']).' · '.self::e($d['tenant_name']).'</p></div><a class="button" href="/admin/onboarding">Close tracker</a></div>'
            .$sharedNotice.'<div class="ob-detail-grid"><div class="ob-timeline"><ol>'.$steps.'</ol><p class="ob-warning">Current blocker: <strong>'.self::e($state['blocker']).'</strong>'.($state['stalled']?' · Stalled for over 48 hours':'').'</p></div>'
            .'<div class="ob-workspace"><h3>DNS ownership & routing</h3>'.$dns.'<p class="muted">CNAME only works if the configured domain target is routed to the ePaper origin. Also add this hostname to Cloudways domain management and its SSL configuration.</p>'.$verify
            .'<div class="ob-rule"></div><h3>Publisher access</h3><p class="muted">'.((int)$d['owner_count']>0?'An active owner/admin membership is present.':'No active publisher owner yet. Create the user in Users, then assign here.').'</p>'.$assign
            .'<div class="ob-rule"></div><h3>Subscription</h3><p>Current service access: <strong>'.self::e((string)($d['service_access_state']??'NOT CONFIGURED')).'</strong></p><a href="/admin/subscriptions">Manage subscriptions</a>'
            .'<div class="ob-rule"></div>'.$confirm.$reassign.'</div></div></section>';
    }
}
