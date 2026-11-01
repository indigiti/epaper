<?php
declare(strict_types=1);

$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    if(!str_starts_with($class,'Digiti\\'))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,7)).'.php';
    if(is_file($file))require_once $file;
});
use Digiti\Admin\SharedAppRecovery;

function verify(bool $condition,string $message):void {
    if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}
$old=[
    'hostname'=>'epaper.dcnnews.co.in','base_url'=>'https://epaper.dcnnews.co.in',
    'domain_type'=>'SAAS_DEFAULT','status'=>'ROUTING_VALID',
    'application_guid'=>'old-app','product_guid'=>'same-product',
    'application_status'=>'ACTIVE','product_status'=>'ACTIVE',
    'tenant_status'=>'ACTIVE','organisation_status'=>'ACTIVE'
];
$installed=[
    'application_guid'=>'shared-app','product_guid'=>'same-product',
    'base_url'=>'https://epaper.digiti.in','status'=>'ACTIVE'
];
$anchor=[
    'hostname'=>'epaper.digiti.in','domain_type'=>'SAAS_DEFAULT','status'=>'ROUTING_VALID',
    'application_guid'=>'shared-app','product_guid'=>'same-product'
];
verify(SharedAppRecovery::eligible($old,$installed,$anchor),
    'DCN canonical misregistration can be converted to shared EP-DI application');
verify(!SharedAppRecovery::eligible($old,[...$installed,'product_guid'=>'wrong-product'],$anchor),
    'Never rebind domain to another product');
verify(!SharedAppRecovery::eligible([...$old,'tenant_status'=>'REMOVED'],$installed,$anchor),
    'Never permit removed publisher tenant');
verify(!SharedAppRecovery::eligible([...$old,'hostname'=>'epaper.digiti.in'],$installed,$anchor),
    'Never transform the shared main canonical domain into publisher alias');
verify(!SharedAppRecovery::eligible([...$old,'domain_type'=>'PLATFORM'],$installed,$anchor),
    'Protect Platform infrastructure hostnames');
verify(!SharedAppRecovery::eligible($old,$installed,[...$anchor,'status'=>'REMOVED']),
    'Shared application must have active routable canonical registration');
verify(!SharedAppRecovery::eligible($old,$installed,[...$anchor,'application_guid'=>'other-app']),
    'Shared base URL must belong to selected application');
$source=file_get_contents($root.'/app/Admin/SharedAppRecovery.php');
$app=file_get_contents($root.'/app/App.php');
$onboarding=file_get_contents($root.'/app/Admin/Onboarding.php');
$dashboard=file_get_contents($root.'/app/Admin/OnboardingDashboard.php');
verify(str_contains($source,"if(\$role!=='SUPER_ADMIN')"),'Superadmin required');
verify(str_contains($app,"if(\$method==='POST')Http::verifyCsrf();"),'Global POST CSRF protection remains enabled');
verify(str_contains($app,'SharedAppRecovery::run(strtolower($m[1]),$method)'),
    'Recovery route registered before general CRUD');
verify(str_contains($source,'confirm_hosted')&&str_contains($source,'confirm_isolation')
    &&str_contains($source,'confirm_hostname')&&str_contains($source,'confirm_app_hostname'),
    'Explicit reviewed source and destination confirmation');
verify(str_contains($source,'domain_type="CUSTOMER_CUSTOM"') &&
    str_contains($source,'status="AWAITING_DNS"'),'Conversion resets verification and changes canonical registration to customer alias');
verify(str_contains($source,'domain.shared_application_rebind'),'Conversion is audited');
verify(str_contains($source,"RedisStore::del('domain:'.\$hostname)"),'Platform domain cache invalidated after conversion');
verify(!str_contains($source,'UPDATE product_tenants')&&!str_contains($source,'UPDATE organisations')
    &&!str_contains($source,'DELETE FROM'), 'No tenant reassignment or cleanup of publisher records');
verify(str_contains($source,'application_redirect_uris'),'New OAuth callback registered on shared app');
verify(str_contains($onboarding,'confirm_dedicated_deployment')&&
    str_contains($dashboard,'confirm_dedicated_deployment'),'New accidental separate canonical registration requires explicit deployment attestation');
echo "PASS Shared ePaper application recovery contracts\n";
