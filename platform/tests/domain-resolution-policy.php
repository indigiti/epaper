<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Domain/Routing/DomainResolutionPolicy.php';
use Digiti\Domain\Routing\DomainResolutionPolicy as Policy;
$fail=[];
$check=static function(bool $ok,string $label)use(&$fail):void{echo ($ok?'PASS':'FAIL')." — ".$label."\n";if(!$ok)$fail[]=$label;};
$record=[
    'status'=>'ACTIVE',
    'product_status'=>'ACTIVE',
    'application_status'=>'ACTIVE',
    'tenant_status'=>'ACTIVE',
    'organisation_status'=>'ACTIVE',
    'product_guid'=>'product-a',
    'organisation_guid'=>'organisation-a',
    'application_product_guid'=>'product-a',
    'tenant_product_guid'=>'product-a',
    'tenant_organisation_guid'=>'organisation-a'
];
$service=['product_guid'=>'product-a'];
$check(Policy::denyReason($record,$service)===null,'active consistent domain');
foreach(['status','product_status','application_status','tenant_status','organisation_status'] as $field){
    $case=$record;$case[$field]='SUSPENDED';
    $check(Policy::denyReason($case,$service)==='DOMAIN_NOT_ACTIVE',"suspended $field denied");
}
foreach(['application_product_guid','tenant_product_guid','tenant_organisation_guid'] as $field){
    $case=$record;$case[$field]='wrong';
    $check(Policy::denyReason($case,$service)==='DOMAIN_NOT_ACTIVE',"mismatched $field denied");
}
$check(Policy::denyReason($record,[])==='DOMAIN_NOT_ACTIVE','unbound service denied');
$check(Policy::denyReason($record,['product_guid'=>'product-b'])==='DOMAIN_NOT_ACTIVE','cross-product service denied');
foreach(['ROUTING_VALID','SSL_PROVISIONING'] as $status){$case=$record;$case['status']=$status;$check(Policy::denyReason($case,$service)==='DOMAIN_NOT_ACTIVE',"pre-active $status cannot bypass approval");}
exit($fail?1:0);
