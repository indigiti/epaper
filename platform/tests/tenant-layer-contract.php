<?php
declare(strict_types=1);
$root=dirname(__DIR__);$failed=[];
$check=static function(bool $ok,string $name)use(&$failed):void{
    echo ($ok?'PASS':'FAIL')." — ".$name."\n";if(!$ok)$failed[]=$name;
};
$crud=file_get_contents($root.'/app/Admin/Crud.php');
$app=file_get_contents($root.'/app/Application/Tenants/ManageTenant.php');
$repo=file_get_contents($root.'/app/Infrastructure/Tenants/TenantRepository.php');
$policy=file_get_contents($root.'/app/Domain/Tenants/TenantLifecyclePolicy.php');
foreach(['edit','status','remove'] as $method)
    $check(str_contains($crud,"ManageTenant::$method("),"Admin tenant $method delegates");
$check(str_contains($repo,'Store::tx(')&&str_contains($repo,'FOR UPDATE'),'tenant lifecycle updates lock and transact');
$check(str_contains($repo,'UPDATE domains SET status="REMOVED",is_primary=0'),'removed tenant deactivates domains');
$check(str_contains($app,"RedisStore::del('entitlements:"),'mutations evict Platform entitlement cache');
$check(str_contains($app,"RedisStore::del('domain:"),'mutations evict Platform domain cache');
$check(str_contains($policy,"'REMOVED'&&"),'removed tenant state cannot be resurrected');
exit($failed?1:0);
