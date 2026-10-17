<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Domain/Identity/TenantBoundaryPolicy.php';
use Digiti\Domain\Identity\TenantBoundaryPolicy as Policy;
$failed=[];
$check=static function(bool $ok,string $name)use(&$failed):void{echo ($ok?'PASS':'FAIL')." — ".$name."\n";if(!$ok)$failed[]=$name;};
$app=['status'=>'ACTIVE','product_guid'=>'product-a'];
$tenant=['status'=>'ACTIVE','organisation_status'=>'ACTIVE','product_status'=>'ACTIVE','product_guid'=>'product-a'];
$service=['product_guid'=>'product-a'];
$check(Policy::denyReason($app,$tenant,$service)===null,'active product, tenant and service');
foreach(['status','organisation_status','product_status'] as $field) {
    $x=$tenant;$x[$field]='SUSPENDED';
    $check(Policy::denyReason($app,$x,$service)==='TENANT_INACTIVE',"inactive $field denied");
}
$x=$tenant;$x['product_guid']='product-b';
$check(Policy::denyReason($app,$x,$service)==='AUTH_PRODUCT_MISMATCH','cross-product tenant denied');
$x=$app;$x['status']='DISABLED';
$check(Policy::denyReason($x,$tenant,$service)==='AUTH_CLIENT_INVALID','inactive application denied');
$check(Policy::denyReason($app,$tenant,[])==='SERVICE_PRODUCT_MISMATCH','unbound service denied');
$check(Policy::denyReason($app,$tenant,['product_guid'=>'product-b'])==='SERVICE_PRODUCT_MISMATCH','cross-product service denied');
exit($failed?1:0);
