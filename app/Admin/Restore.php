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
        if($method!=='POST') Http::redirect('/admin');
        Auth::requireAdmin();
        if(!isset(self::TYPES[$type]) || !preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i',$guid))
            throw new \InvalidArgumentException('Invalid restore request.');
        $cfg=self::TYPES[$type];
        $bin=Uuid::toBinary($guid);
        $table=$cfg['table'];$key=$cfg['key'];
        $record=Store::one("SELECT * FROM $table WHERE $key=?",[$bin]);
        if(!$record) throw new \InvalidArgumentException('Record not found.');
        if($record['status']!=='REMOVED') throw new \InvalidArgumentException('Only archived records can be restored.');
        self::checkDependencies($type,$record);
        $target=$type==='domains'?'AWAITING_DNS':($type==='service-accounts'?'REVOKED':'SUSPENDED');
        Store::tx(function()use($type,$guid,$cfg,$table,$key,$bin,$target,$record):void{
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
            // Keep both the original removal event and this new recovery event.
            Audit::log('registry.restore',$cfg['entity'],$guid,'SUCCESS',[
                'previous_status'=>'REMOVED','restored_status'=>$target,
                'dependent_records_restored'=>false
            ]);
        });
        self::invalidate($type,$record,$guid);
        View::flash('Record restored to '.$target.'. Dependent access, subscriptions and domain readiness were NOT automatically restored.');
        Http::redirect('/admin/'.$type);
    }

    private static function checkDependencies(string $type,array $record): void
    {
        $check=static function(?string $table,?string $key,?string $foreign,string $label): void {
            if($table===null||$foreign===null)return;
            $status=Store::value("SELECT status FROM $table WHERE $key=?",[$foreign]);
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
            $app=Store::one('SELECT base_url FROM applications WHERE application_guid=?',[$record['application_guid']]);
            $canonical=(string)(parse_url((string)($app['base_url']??''),PHP_URL_HOST)?:'');
            if($canonical!=='' && strcasecmp($canonical,(string)$record['hostname'])===0)
                throw new \InvalidArgumentException('The canonical application domain cannot be restored through customer-domain tools.');
            // Keep the original hostname and its unique key. Never insert a duplicate record.
            $duplicate=Store::value('SELECT COUNT(*) FROM domains WHERE normalized_hostname=? AND domain_guid<>?',[
                $record['normalized_hostname'],$record['domain_guid']
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
            foreach(Store::all("SELECT hostname FROM domains WHERE $column=?",[$row[$column]]) as $d)
                RedisStore::del('domain:'.$d['hostname']);
        }
        if($type==='product-tenants') RedisStore::del('entitlements:'.$guid);
    }
}
