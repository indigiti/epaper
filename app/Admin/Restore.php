<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\{Audit,Auth,Http,RedisStore,Store,Uuid,View};

/**
 * Recover archived Platform records without pretending that all dependent
 * records, users, invoices, credentials or domain certificates are restored.
 *
 * There is deliberately no hard delete. Restored non-domain entities are
 * SUSPENDED (service accounts REVOKED). Domains restart AWAITING_DNS.
 */
final class Restore
{
    private const TYPES=[
        'organisations'=>['table'=>'organisations','key'=>'organisation_guid','entity'=>'organisation','parent'=>null],
        'users'=>['table'=>'platform_users','key'=>'user_guid','entity'=>'platform_user','parent'=>null],
        'products'=>['table'=>'products','key'=>'product_guid','entity'=>'product','parent'=>null],
        'applications'=>['table'=>'applications','key'=>'application_guid','entity'=>'application','parent'=>'product'],
        'product-tenants'=>['table'=>'product_tenants','key'=>'product_tenant_guid','entity'=>'product_tenant','parent'=>'org_product'],
        'domains'=>['table'=>'domains','key'=>'domain_guid','entity'=>'domain','parent'=>'domain'],
        'service-accounts'=>['table'=>'service_accounts','key'=>'service_account_guid','entity'=>'service_account','parent'=>'product_optional'],
        'commercial/plans'=>['table'=>'plans','key'=>'plan_guid','entity'=>'plan','parent'=>'product']
    ];

    public static function run(string $type,string $guid,string $method): never
    {
        Auth::requireAdmin();
        if(!isset(self::TYPES[$type]) || !preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i',$guid))
            throw new \InvalidArgumentException('Invalid restore request.');
        if(!in_array($method,['GET','POST'],true)) {
            http_response_code(405);
            header('Allow: GET, POST');
            View::layout('Method not allowed','<section class="card">Use the restore confirmation page.</section>');
        }
        $cfg=self::TYPES[$type];
        $bin=Uuid::toBinary($guid);
        $table=$cfg['table'];$key=$cfg['key'];
        $record=Store::one("SELECT * FROM $table WHERE $key=?",[$bin]);
        if(!$record) throw new \InvalidArgumentException('Archived record not found.');
        if($record['status']!=='REMOVED') {
            View::flash('This record is not archived. Open its existing record instead.');
            Http::redirect('/admin/'.$type);
        }
        $label=self::recordLabel($type,$record);
        $canonical=$type==='domains' && self::canonicalDomain($record);
        if($canonical) self::requireSystemDomainAdmin();
        if($method==='GET') {
            $blocker=null;
            try {self::checkDependencies($type,$record);}
            catch(\InvalidArgumentException $e){$blocker=$e->getMessage();}
            $target=$type==='domains'?'AWAITING_DNS':($type==='service-accounts'?'REVOKED':'SUSPENDED');
            $body='<section class="card platform-restore-review"><h2>'.($canonical?'Restore ePaper application hostname':'Recover an existing record').'</h2>'
                .'<p>This record already exists. Restore it instead of attempting to add another record with the same identifier.</p>'
                .'<dl><div><dt>Record</dt><dd>'.View::esc($label).'</dd></div>'
                .'<div><dt>Current state</dt><dd>Archived (REMOVED)</dd></div>'
                .'<div><dt>After recovery</dt><dd>'.View::esc($target).'</dd></div></dl>'
                .'<p class="muted">The original record and history are preserved. Dependent accounts, privileges, DNS and SSL readiness are not silently reactivated.</p>'
                .($canonical?'<div class="platform-restore-warning"><strong>Canonical application domain:</strong> This hostname belongs to the installed ePaper application, not a separate publisher. Restore the original record only; its product, application and tenant must all be active and correctly related. Routing will restart at AWAITING_DNS, not go live automatically. No ePaper content or other publisher data is moved.</div>':'')
                .($blocker?'<div class="platform-restore-warning" role="alert"><strong>Recovery blocked:</strong> '.View::esc($blocker).'</div>':'')
                .($type==='domains'?self::dependencyGuide($record):'')
                .($canonical?self::freshStartAlternative($record):'')
                .'<form method="post" action="/admin/'.View::esc($type).'/'.View::esc($guid).'/restore">'
                .'<input type="hidden" name="_csrf" value="'.View::esc(Http::csrf()).'">'
                .'<label>Type <strong>'.View::esc($label).'</strong> to confirm'
                .'<input name="confirm_record" required autocomplete="off" placeholder="'.View::esc($label).'"></label>'
                .'<label class="platform-restore-check"><input type="checkbox" name="confirm_restore" value="1" required>'
                .' I understand this reopens the archived record and requires follow-up checks.</label>'
                .($canonical?'<label class="platform-restore-check"><input type="checkbox" name="confirm_canonical" value="1" required>'
                    .' I understand this is the main ePaper application hostname. Restoring it does not restore an archived product or activate public routing.</label>':'')
                .'<div class="form-action"><button type="submit" class="primary"'.($blocker?' disabled':'').'>'.($canonical?'Restore application hostname':'Restore existing record').'</button>'
                .'<a class="button" href="/admin/'.View::esc($type).'">Cancel</a></div></form>'
                .'</section>';
            View::layout('Restore archived record',$body,['subtitle'=>$label]);
        }
        if(trim((string)($_POST['confirm_record']??''))!==$label
            ||($_POST['confirm_restore']??'')!=='1')
            throw new \InvalidArgumentException('Type the exact record name and confirm the recovery.');
        if($canonical && ($_POST['confirm_canonical']??'')!=='1')
            throw new \InvalidArgumentException('Confirm the additional application-hostname recovery warning.');
        self::checkDependencies($type,$record);
        $target=$type==='domains'?'AWAITING_DNS':($type==='service-accounts'?'REVOKED':'SUSPENDED');
        Store::tx(function()use($type,$guid,$cfg,$table,$key,$bin,$target):void{
            if($type==='domains') {
                $affected=Store::exec('UPDATE domains SET status="AWAITING_DNS",is_primary=0,
                    dns_metadata=NULL,edge_metadata=NULL,verified_at=NULL,ssl_ready_at=NULL,
                    activated_at=NULL,last_checked_at=NULL,updated_at=NOW(6)
                    WHERE domain_guid=? AND status="REMOVED"',[$bin]);
            } elseif($type==='service-accounts') {
                $affected=Store::exec('UPDATE service_accounts SET status="REVOKED" WHERE service_account_guid=? AND status="REMOVED"',[$bin]);
            } else {
                $affected=Store::exec("UPDATE $table SET status=?,updated_at=NOW(6) WHERE $key=? AND status='REMOVED'",[$target,$bin]);
            }
            if($affected!==1)throw new \RuntimeException('The record changed during restore. Reload and retry.');
            Audit::log('registry.restore',$cfg['entity'],$guid,'SUCCESS',[
                'previous_status'=>'REMOVED','restored_status'=>$target,
                'dependent_records_restored'=>false
            ]);
        });
        self::invalidate($type,$record,$guid);
        View::flash($canonical
            ?'Canonical ePaper hostname restored to AWAITING_DNS. Product and application remain unchanged; complete routing verification before go-live.'
            :'Restored to '.$target.'. Review the publisher/application link and complete onboarding before publishing.');
        $next=$canonical
            ?'/admin/onboarding?domain='.rawurlencode($guid).'#domain-review'
            :($type==='domains'
                ?'/admin/domains/'.rawurlencode($guid).'/edit'
                :(in_array($type,['products','organisations','applications','product-tenants'],true)
                    ?'/admin/'.$type.'/'.rawurlencode($guid).'/edit'
                    :'/admin/'.$type));
        Http::redirect($next);
    }

    private static function recordLabel(string $type,array $record): string
    {
        $columns=match($type){
            'domains'=>['hostname'],
            'organisations'=>['organisation_code','display_name'],
            'users'=>['email','display_name'],
            'products'=>['product_key','name'],
            'applications'=>['application_key','name'],
            'product-tenants'=>['display_name'],
            'service-accounts'=>['name'],
            'commercial/plans'=>['plan_key','name'],
            default=>['name']
        };
        foreach($columns as $column) {
            if(trim((string)($record[$column]??''))!=='')return (string)$record[$column];
        }
        return 'Archived record';
    }

    /**
     * The ePaper application's canonical domain is allowed a separate recovery
     * path. Never treat a Platform/Platform Auth infrastructure hostname as an
     * ePaper customer domain.
     */
    private static function canonicalDomain(array $record): bool
    {
        if(!in_array((string)($record['domain_type']??''),['SAAS_DEFAULT','CUSTOMER_CUSTOM','CUSTOMER_SUBDOMAIN'],true))
            return false;
        $app=Store::one('SELECT a.base_url,p.product_key FROM applications a
            JOIN products p ON p.product_guid=a.product_guid WHERE a.application_guid=?',[
            Uuid::toBinary((string)$record['application_guid'])
        ]);
        if(!$app || $app['product_key']!=='EPAPER')return false;
        $canonical=(string)(parse_url((string)$app['base_url'],PHP_URL_HOST)?:'');
        return $canonical!=='' && strcasecmp((string)$record['hostname'],$canonical)===0;
    }

    /**
     * Present a read-only recovery plan from real linked rows. Nothing is
     * changed here. Each operation opens the existing protected edit/restore
     * form and still requires the operator's explicit confirmation.
     */
    private static function dependencyGuide(array $domain): string
    {
        $parents=[
            ['Product','products','product_guid','product_key','name'],
            ['Organisation','organisations','organisation_guid','organisation_code','display_name'],
            ['Application','applications','application_guid','application_key','name'],
            ['Publisher tenant','product-tenants','product_tenant_guid',null,'display_name'],
        ];
        $html='<section class="platform-dependency-guide" aria-labelledby="platform-dependency-heading">'
            .'<h3 id="platform-dependency-heading">Repair required records in this order</h3>'
            .'<p>These are the records already linked to this website. Do not create a new EPAPER product or another domain. '
            .'Restore archived records, activate suspended ones, then return here to restore the hostname.</p><ol>';
        $allReady=true;
        $rows=[];
        foreach($parents as [$name,$route,$column,$codeColumn,$titleColumn]) {
            $guid=(string)($domain[$column]??'');
            $table=str_replace('-','_',$route);
            $table=$route==='product-tenants'?'product_tenants':$table;
            $lookup=match($route){
                'products'=>'product_guid',
                'organisations'=>'organisation_guid',
                'applications'=>'application_guid',
                'product-tenants'=>'product_tenant_guid',
                default=>throw new \LogicException('Unsupported dependency'),
            };
            $columns=$codeColumn ? "$codeColumn record_code,$titleColumn display_label,status" : "$titleColumn display_label,status";
            $row=$guid!==''?Store::one("SELECT $columns FROM $table WHERE $lookup=? LIMIT 1",[
                Uuid::toBinary($guid)
            ]):null;
            $status=$row===null?'MISSING':(string)$row['status'];
            $ready=$status==='ACTIVE';
            if(!$ready)$allReady=false;
            $display=$row?trim((string)($row['record_code']??$row['display_label']??$name)):$name.' (record missing)';
            $url='/admin/'.$route.'/'.rawurlencode($guid);
            $task=$status==='REMOVED'?'Restore existing '.$name:
                ($status==='ACTIVE'?'Ready':($status==='MISSING'?'Contact administrator':'Review and activate '.$name));
            $link=$status==='REMOVED'?$url.'/restore':$url.'/edit';
            $action=($ready||$status==='MISSING')
                ?'<span class="platform-dependency-state">'.($ready?'Ready':'Manual investigation needed').'</span>'
                :'<a class="button" href="'.View::esc($link).'">'.View::esc($task).'</a>';
            $html.='<li class="platform-dependency-item">'
                .'<div><strong>'.View::esc($name).': '.View::esc($display).'</strong>'
                .'<small>'.View::esc($status).($status==='REMOVED'?' — restore first, then activate from its Edit screen':'').'</small></div>'
                .$action.'</li>';
            $rows[$column]=$row;
        }
        $html.='</ol>';
        $productKey=(string)($rows['product_guid']['record_code']??'');
        if($productKey!==''&&$productKey!=='EPAPER')
            $html.='<div class="platform-restore-warning">This hostname refers to product '.View::esc($productKey)
                .', not EPAPER. Do not restore it as the main ePaper site without correcting the original relationship.</div>';
        if($allReady) $html.='<p><strong>All parent records are active.</strong> Confirm the original domain below to restart its verification.</p>';
        $html.='<p><a href="/admin/domains/'.rawurlencode((string)$domain['domain_guid']).'/restore">'
            .'Return to this hostname after repairing the records</a></p></section>';
        return $html;
    }

    /**
     * Safe opt-out from restoring the prior publisher/product graph. This page
     * is informational by design: there is no hidden reset or SQL DELETE.
     * A fresh EPAPER product cannot coexist with the archived EPAPER product
     * in this database because the integration key is globally unique.
     */
    private static function freshStartAlternative(array $domain): string
    {
        $productGuid=(string)$domain['product_guid'];
        $productBin=Uuid::toBinary($productGuid);
        $countApps=(int)Store::value('SELECT COUNT(*) FROM applications WHERE product_guid=?',[$productBin]);
        $countTenants=(int)Store::value('SELECT COUNT(*) FROM product_tenants WHERE product_guid=?',[$productBin]);
        $countDomains=(int)Store::value('SELECT COUNT(*) FROM domains WHERE product_guid=?',[$productBin]);
        $counts='<p class="platform-fresh-impact"><strong>Records linked to the existing product:</strong> '
            .$countApps.' application(s), '.$countTenants.' publisher tenant(s), '
            .$countDomains.' domain(s). No records will be changed by opening this option.</p>';
        $staging=StagingReset::enabled();
        $choice=$staging
            ?'<p><a class="button" href="/admin/staging-reset">View staging test-domain reset</a>'
                .' <small>That tool only restarts test-domain onboarding; it does not delete products or create a blank Platform database.</small></p>'
            :'<div class="platform-fresh-restricted"><strong>Fresh-install reset is not available here.</strong> '
                .'Use a separately configured, isolated staging application and database. '
                .'No reset or deletion of the live Platform database is allowed from this screen.</div>';
        return '<section class="platform-fresh-alternative" id="fresh-setup-alternative">'
            .'<details><summary><span class="platform-fresh-title">I do not want to restore the old product or other records</span>'
            .'<span class="platform-fresh-subtitle">See how to start fresh without reactivating this archived setup</span></summary>'
            .'<div class="platform-fresh-content">'
            .'<p><strong>That is a valid choice.</strong> The original EPAPER product, AMG organisation, '
            .'application, tenant and hostname can remain archived. '
            .'This option does not recover or reactivate any of them.</p>'
            .$counts
            .'<p><strong>Why you cannot simply add the same website again:</strong> The EPAPER product key and '
            .'epaper.digiti.in hostname remain reserved in this database. The ePaper reader integration '
            .'also expects the EPAPER product. Ignoring those relationships would break routing '
            .'or cross-publisher isolation.</p>'
            .'<h4>Start fresh safely</h4><ol>'
            .'<li>Preserve a verified backup of Platform and ePaper databases, media and private configuration.</li>'
            .'<li>Provision a <strong>separate staging Cloudways application</strong> with a new empty database, '
            .'independent storage and Redis, and a staging hostname.</li>'
            .'<li>Install a fresh Platform configuration there, create the EPAPER product and first test publisher, '
            .'then verify login, reader content isolation and routing using the staging hostname.</li>'
            .'<li>Only after staging acceptance, prepare a controlled production migration/cutover for '
            .'epaper.digiti.in. That requires separate data migration, DNS, SSL and cache checks.</li>'
            .'</ol>'.$choice
            .'<div class="platform-fresh-actions">'
            .'<a class="button" href="/admin/domains">Do not restore — leave existing records archived</a>'
            .'</div></div></details></section>';
    }

    private static function requireSystemDomainAdmin(): void
    {
        $admin=Auth::requireAdmin();
        $role=Store::value('SELECT role_key FROM platform_admins WHERE admin_guid=?',[
            Uuid::toBinary((string)$admin['admin_guid'])
        ]);
        if($role!=='SUPER_ADMIN')throw new \InvalidArgumentException('Only a Platform SUPER_ADMIN may recover the canonical ePaper hostname.');
    }

    private static function checkDependencies(string $type,array $record): void
    {
        $check=static function(?string $table,?string $key,?string $foreign,string $label): void {
            if($table===null||$foreign===null)return;
            $status=Store::value("SELECT status FROM $table WHERE $key=?",[Uuid::toBinary($foreign)]);
            if($status===null) throw new \InvalidArgumentException("The linked $label record is missing. Do not create a replacement without reviewing the original references.");
            if($status==='REMOVED') throw new \InvalidArgumentException("The linked $label is archived (REMOVED). Restore it, then activate it before restoring this hostname.");
        };
        if(in_array($type,['applications','product-tenants','domains','commercial/plans'],true)
            || ($type==='service-accounts' && $record['product_guid']!==null)) {
            $check('products','product_guid',$record['product_guid']??null,'product');
        }
        if($type==='product-tenants'||$type==='domains')
            $check('organisations','organisation_guid',$record['organisation_guid']??null,'organisation');
        if($type==='domains') {
            $check('applications','application_guid',$record['application_guid']??null,'application');
            $check('product_tenants','product_tenant_guid',$record['product_tenant_guid']??null,'publisher tenant');
            $parents=Store::one('SELECT p.status product_status,o.status organisation_status,
                a.status application_status,pt.status tenant_status,
                p.product_guid,pt.product_guid tenant_product_guid,
                a.product_guid application_product_guid,pt.organisation_guid tenant_organisation_guid
                FROM domains d JOIN products p ON p.product_guid=d.product_guid
                JOIN organisations o ON o.organisation_guid=d.organisation_guid
                JOIN applications a ON a.application_guid=d.application_guid
                JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid
                WHERE d.domain_guid=? LIMIT 1',[Uuid::toBinary((string)$record['domain_guid'])]);
            if(!$parents)throw new \InvalidArgumentException('The domain has missing parent records. Correct the original product, application or publisher first.');
            foreach(['product','organisation','application','tenant'] as $name) {
                if($parents[$name.'_status']!=='ACTIVE')
                    throw new \InvalidArgumentException('The '.$name.' is '.$parents[$name.'_status'].'. Activate it before restoring this domain. Do not create another product.');
            }
            if($parents['product_guid']!==$parents['application_product_guid']
                ||$parents['product_guid']!==$parents['tenant_product_guid']
                ||$record['organisation_guid']!==$parents['tenant_organisation_guid'])
                throw new \InvalidArgumentException('Domain, application and publisher belong to different products or organisations. Correct the mapping before recovery.');
            $canonical=self::canonicalDomain($record);
            if(!$canonical && !in_array((string)$record['domain_type'],['CUSTOMER_CUSTOM','CUSTOMER_SUBDOMAIN'],true))
                throw new \InvalidArgumentException('System infrastructure hostnames require a separate infrastructure recovery process.');
            // Keep the original hostname and its unique key. Never insert a duplicate record.
            $duplicate=Store::value('SELECT COUNT(*) FROM domains WHERE normalized_hostname=? AND domain_guid<>?',[
                $record['normalized_hostname'],Uuid::toBinary($record['domain_guid'])
            ]);
            if((int)$duplicate>0)throw new \InvalidArgumentException('Another domain already uses this hostname.');
        }
    }

    private static function invalidate(string $type,array $row,string $guid):void
    {
        if($type==='domains') {
            RedisStore::del('domain:'.$row['hostname']);
            return;
        }
        $column=match($type) {
            'organisations'=>'organisation_guid',
            'products'=>'product_guid',
            'applications'=>'application_guid',
            'product-tenants'=>'product_tenant_guid',
            default=>null,
        };
        if($column!==null) {
            // Static whitelist; never use user input as an SQL identifier.
            foreach(Store::all("SELECT hostname FROM domains WHERE $column=?",[Uuid::toBinary($row[$column])]) as $d)
                RedisStore::del('domain:'.$d['hostname']);
        }
        if($type==='product-tenants') RedisStore::del('entitlements:'.$guid);
    }
}
