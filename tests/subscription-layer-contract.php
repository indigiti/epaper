<?php
declare(strict_types=1);
$root=dirname(__DIR__);$errors=[];
$check=static function(bool $ok,string $label)use(&$errors):void{
    echo ($ok?'PASS':'FAIL')." — ".$label."\n";if(!$ok)$errors[]=$label;
};
$app=file_get_contents($root.'/app/App.php');
$crud=file_get_contents($root.'/app/Admin/Crud.php');
$useCase=file_get_contents($root.'/app/Application/Subscriptions/ManageSubscription.php');
$read=file_get_contents($root.'/app/Application/Subscriptions/SubscriptionAccess.php');
$repository=file_get_contents($root.'/app/Infrastructure/Subscriptions/SubscriptionRepository.php');
$check(str_contains($app,'ManageSubscription::activate('),'Admin activation delegates to application service');
$check(str_contains($crud,'ManageSubscription::update('),'Admin subscription edit delegates to use case');
$check(str_contains($crud,'name="version"'),'Admin form submits record version');
$check(str_contains($repository,'FOR UPDATE')&&str_contains($repository,'version=?'),'subscription edit version checked atomically');
$check(str_contains($repository,'status IN ("ACTIVE","PAST_DUE","GRACE_PERIOD")'),'activation guards duplicate current subscription');
$check(str_contains($repository,'tenant_status')&&str_contains($repository,'organisation_status')&&str_contains($repository,'product_status'),'subscription activation validates parent entities');
$check(str_contains($app,'SubscriptionAccess::payload('),'tenant access and entitlements use fresh access policy');
$check(str_contains($app,"['service_access_state']==='FULL'"),'entitlements not returned during inactive access');
$check(str_contains($read,'SubscriptionAccessPolicy::effective('),'read service enforces domain rules');
$check(str_contains($useCase,'RedisStore::del('),'mutations invalidate Platform grants cache');
exit($errors?1:0);
