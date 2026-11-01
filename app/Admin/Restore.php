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
        if($method==='GET') {
            $blocker=null;
            try {self::checkDependencies($type,$record);}
            catch(\InvalidArgumentException $e){$blocker=$e->getMessage();}
            $target=$type==='domains'?'AWAITING_DNS':($type==='service-accounts'?'REVOKED':'SUSPENDED');
            $body='<section class="card platform-restore-review"><h2>Recover an existing record</h2>'
                .'<p>This record already exists. Restore it instead of attempting to add another record with the same identifier.</p>'
                .'<dl><div><dt>Record</dt><dd>'.View::esc($label).'</dd></div>'
                .'<div><dt>Current state</dt><dd>Archived (REMOVED)</dd></div>'
                .'<div><dt>After recovery</dt><dd>'.View::esc($target).'</dd></div></dl>'
                .'<p class="muted">The original record and history are preserved. Dependent accounts, privileges, DNS and SSL readiness are not silently reactivated.</p>'
                .($blocker?'<div class="platform-restore-warning" role="alert"><strong>Recovery blocked:</strong> '.View::esc($blocker).'</div>':'')
                .'<form method="post" action="/admin/'.View::esc($type).'/'.View::esc($guid).'/restore">'
                .'<input type="hidden" name="_csrf" value="'.View::esc(Http::csrf()).'">'
                .'<label>Type <strong>'.View::esc($label).'</strong> to confirm'
                .'<input name="confirm_record" required autocomplete="off" placeholder="'.View::esc($label).'"></label>'
                .'<label class="platform-restore-check"><input type="checkbox" name="confirm_restore" value="1" required>'
                .' I understand this reopens the archived record and requires follow-up checks.</label>'
                .'<div class="form-action"><button type="submit" class="primary"'.($blocker?' disabled':'').'>Restore existing record</button>'
                .'<a class="button" href="/admin/'.View::esc($type).'">Cancel</a></div></form>'
                .'</section>';
            View::layout('Restore archived record',$body,['subtitle'=>$label]);
        }
        if(trim((string)($_POST['confirm_record']??''))!==$label
            ||($_POST['confirm_restore']??'')!=='1')
            throw new \InvalidArgumentException('Type the exact record name and confirm the recovery.');
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
        View::flash('Restored to '.$target.'. Review the publisher/application link and complete onboarding before publishing.');
        $next=$type==='domains'
            ?'/admin/domains/'.rawurlencode($guid).'/edit'
            :'/admin/'.$type;
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

    private static function checkDependencies(string $type,array $record): void
    {
        $check=static function(?string $table,?string $key,?string $foreign,string $label): void {
            if($table===null||$foreign===null)return;
            $status=Store::value("SELECT status FROM $table WHERE $key=?",[Uuid::toBinary($foreign)]);
            if($status===null||$status==='REMOVED')
                throw new \InvalidArgumentException("Restore the $label before this record.");
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
            if(!in_array($record['domain_type'],['CUSTOMER_CUSTOM','CUSTOMER_SUBDOMAIN'],true))
                throw new \InvalidArgumentException('System domains require a separate infrastructure recovery process.');
            $app=Store::one('SELECT base_url FROM applications WHERE application_guid=?',[Uuid::toBinary($record['application_guid'])]);
            $canonical=(string)(parse_url((string)($app['base_url']??''),PHP_URL_HOST)?:'');
            if($canonical!=='' && strcasecmp($canonical,(string)$record['hostname'])===0)
                throw new \InvalidArgumentException('The canonical application domain cannot be restored through customer-domain tools.');
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
