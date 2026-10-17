<?php
declare(strict_types=1);
/** Domain lifecycle and tenant isolation against DISPOSABLE CI MariaDB only. */
if(getenv('EPDI_BILLING_INTEGRATION')!=='disposable-ci') {
    fwrite(STDERR,"Refusing to run domain mutations outside disposable CI.\n");
    exit(2);
}
require dirname(__DIR__).'/app/bootstrap.php';
use Digiti\Core\{Store,Uuid};
use Digiti\Application\Admin\RegistryCreates;
use Digiti\Application\Routing\{ManageDomain,RemoveDomain,ResolveDomain,DomainResolutionFailure};

$failed=[];
$assert=static function(bool $ok,string $label)use(&$failed):void{
    echo ($ok?'PASS':'FAIL')." — $label\n";
    if(!$ok)$failed[]=$label;
};
$id=static fn():string=>Uuid::v7();
$bin=static fn(string $x):string=>Uuid::toBinary($x);
$org=$id();$prod=$id();$tenant=$id();$app=$id();
Store::exec('INSERT INTO organisations(organisation_guid,organisation_code,legal_name,display_name,status,created_at,updated_at) VALUES(?,"CI_ROUTING_ORG","CI Routing","CI Routing","ACTIVE",NOW(6),NOW(6))',[$bin($org)]);
Store::exec('INSERT INTO products(product_guid,product_key,name,status,created_at,updated_at) VALUES(?,"EPAPER","CI ePaper","ACTIVE",NOW(6),NOW(6))',[$bin($prod)]);
Store::exec('INSERT INTO product_tenants(product_tenant_guid,organisation_guid,product_guid,display_name,status,created_at,updated_at) VALUES(?,?,?,"CI ePaper tenant","ACTIVE",NOW(6),NOW(6))',[$bin($tenant),$bin($org),$bin($prod)]);
Store::exec('INSERT INTO applications(application_guid,product_guid,application_key,name,base_url,status,created_at,updated_at) VALUES(?,?,"CI_EPAPER_ROUTING_APP","CI Routing app","https://epaper.example.invalid","ACTIVE",NOW(6),NOW(6))',[$bin($app),$bin($prod)]);
$host1='ci-paper-a.example.invalid';
$host2='ci-paper-b.example.invalid';
$first=RegistryCreates::domain(['hostname'=>$host1,'application_guid'=>$app,'product_tenant_guid'=>$tenant,'is_primary'=>'1']);
$second=RegistryCreates::domain(['hostname'=>$host2,'application_guid'=>$app,'product_tenant_guid'=>$tenant,'is_primary'=>'1']);
$assert($first['host']===$host1 && $second['host']===$host2,'Domain creation normalizes and retains hostnames');
$all=Store::all('SELECT domain_guid,hostname,status,is_primary FROM domains WHERE product_tenant_guid=? ORDER BY hostname',[$bin($tenant)]);
$assert(count($all)===2,'Two domain records created on one tenant');
$assert(array_reduce($all,static fn($v,$row):bool=>$v&&$row['status']==='AWAITING_DNS'&&(int)$row['is_primary']===0,true),'New unapproved domains are never primary');
$map=[];foreach($all as $r)$map[$r['hostname']]=$r['domain_guid'];
$a=$map[$host1];$b=$map[$host2];
$assert((int)Store::value('SELECT COUNT(*) FROM outbox_events WHERE tenant_guid=? AND event_type="cache.invalidate"',[$bin($tenant)])>=2,'Creation queues signed ePaper invalidations');
$deny=static function(string $host)use($prod):bool{
    try{ResolveDomain::execute($host,['product_guid'=>$prod]);return false;}
    catch(DomainResolutionFailure){return true;}
};
$assert($deny($host1),'Pre-approval hostname cannot resolve via product API');

$manual='{"provider":"manual","status":"ROUTING_VALID"}';
Store::exec('UPDATE domains SET status="ROUTING_VALID",edge_metadata=? WHERE product_tenant_guid=?',[$manual,$bin($tenant)]);
ManageDomain::activate($a);
ManageDomain::activate($b);
$assert(!$deny($host1),'Manually verified and approved domain resolves');
ManageDomain::edit($a,['status'=>'ACTIVE','domain_type'=>'CUSTOMER_CUSTOM','is_primary'=>'1']);
$assert((int)Store::value('SELECT is_primary FROM domains WHERE domain_guid=?',[$bin($a)])===1,'Edit sets primary after activation');
ManageDomain::edit($b,['status'=>'ACTIVE','domain_type'=>'CUSTOMER_SUBDOMAIN','is_primary'=>'1']);
$assert((int)Store::value('SELECT is_primary FROM domains WHERE domain_guid=?',[$bin($a)])===0,'Switching primary unsets prior primary');
$assert((int)Store::value('SELECT COUNT(*) FROM domains WHERE product_tenant_guid=? AND status="ACTIVE" AND is_primary=1',[$bin($tenant)])===1,'Exactly one active primary hostname per tenant');

$rejected=false;
try{ManageDomain::edit($b,['status'=>'ACTIVE','domain_type'=>'PLATFORM_AUTH','is_primary'=>'1']);}
catch(InvalidArgumentException){$rejected=true;}
$assert($rejected,'Customer editor rejects reserved internal domain type');
$assert((string)Store::value('SELECT domain_type FROM domains WHERE domain_guid=?',[$bin($b)])==='CUSTOMER_SUBDOMAIN','Forbidden type change was rolled back');

ManageDomain::edit($b,['status'=>'SUSPENDED','domain_type'=>'CUSTOMER_SUBDOMAIN','is_primary'=>'1']);
$assert($deny($host2),'Suspension immediately prevents hostname resolution');
$assert((int)Store::value('SELECT is_primary FROM domains WHERE domain_guid=?',[$bin($b)])===0,'Suspension clears primary');
$reactivationDenied=false;
try{ManageDomain::edit($b,['status'=>'ACTIVE','domain_type'=>'CUSTOMER_SUBDOMAIN','is_primary'=>'1']);}
catch(DomainException){$reactivationDenied=true;}
$assert($reactivationDenied,'Suspended hostname cannot be directly reactivated from editor');
ManageDomain::resume($b);
$assert(Store::value('SELECT status FROM domains WHERE domain_guid=?',[$bin($b)])==='AWAITING_DNS','Resume requires fresh verification');
$assert($deny($host2),'Resumed hostname remains blocked pending approval');
$blocked=false;
try{ManageDomain::activate($b);}catch(DomainException){$blocked=true;}
$assert($blocked,'Resume cannot skip verification');
Store::exec('UPDATE domains SET status="SSL_PROVISIONING",edge_metadata=? WHERE domain_guid=?',['{"provider":"cloudflare","status":"SSL_PROVISIONING"}',$bin($b)]);
$blocked=false;
try{ManageDomain::activate($b);}catch(DomainException){$blocked=true;}
$assert($blocked,'Cloudflare certificate pending prevents activation');
Store::exec('UPDATE domains SET edge_metadata=? WHERE domain_guid=?',['{"provider":"cloudflare","status":"ACTIVE"}',$bin($b)]);
ManageDomain::activate($b);
$assert(!$deny($host2),'Active Cloudflare edge can be explicitly approved');

RemoveDomain::execute($b);
$assert(Store::value('SELECT status FROM domains WHERE domain_guid=?',[$bin($b)])==='REMOVED','Remove is soft deletion with historic state');
$assert($deny($host2),'Removed hostname cannot resolve');
$blocked=false;
try{ManageDomain::resume($b);}catch(DomainException){$blocked=true;}
$assert($blocked,'Removed hostname cannot resume');
$blocked=false;
try{ManageDomain::edit($b,['status'=>'REMOVED','domain_type'=>'CUSTOMER_CUSTOM']);}catch(DomainException){$blocked=true;}
$assert($blocked,'Removed hostname cannot be edited');
$assert((int)Store::value('SELECT COUNT(*) FROM outbox_events WHERE tenant_guid=? AND event_type="cache.invalidate"',[$bin($tenant)])>=8,'Lifecycle mutations enqueue product cache invalidations');

exit($failed?1:0);
