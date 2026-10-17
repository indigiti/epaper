<?php
declare(strict_types=1);
$root=dirname(__DIR__);$failed=[];
$check=static function(bool $ok,string $msg)use(&$failed):void{echo ($ok?'PASS':'FAIL')." — $msg\n";if(!$ok)$failed[]=$msg;};
$crud=file_get_contents($root.'/app/Admin/Crud.php');
$app=file_get_contents($root.'/app/App.php');
$worker=file_get_contents($root.'/bin/worker.php');
$outbox=file_get_contents($root.'/app/Application/Integration/CacheInvalidationOutbox.php');
$sender=file_get_contents($root.'/app/Infrastructure/Integration/InvalidationDelivery.php');
$receiver=file_get_contents(dirname($root).'/codebase/app/Services/PlatformInvalidation.php');
$epaperApp=file_get_contents(dirname($root).'/codebase/app/App.php');
foreach(['organisation'=>'organisation','user'=>'user','product'=>'product','application'=>'application',
'serviceAccount'=>'serviceAccount','feature'=>'feature','plan'=>'plan','price'=>'price'] as $label=>$method)
    $check(str_contains($crud,'RegistryMutations::'.$method.'('),"Admin $label writes delegate Application");
foreach(['organisation','user','product','application','tenant','domain','serviceAccount'] as $method)
    $check(str_contains($app,'RegistryCreates::'.$method.'('),"Admin $method creates delegate Application");
$check(str_contains($crud,'ManageTenant::edit(')&&str_contains($crud,'ManageSubscription::update('),'existing layered tenant/subscription routes preserved');
$check(str_contains($outbox,'stageForTenant(')&&str_contains($outbox,'stageForParent('),'tenant- and parent-scope invalidation events staged');
$check(str_contains($outbox,'INSERT INTO outbox_events'),'durable outgoing events persisted');
$check(str_contains($sender,'hash_hmac(')&&str_contains($sender,'CURLOPT_FOLLOWLOCATION=>false'),'signature and redirect protection on delivery');
$check(str_contains($sender,'CURLOPT_SSL_VERIFYPEER=>true'),'sender uses verified TLS');
$check(str_contains($worker,"case 'cache.invalidate':")&&str_contains($worker,'InvalidationDelivery::send('),'worker dispatches invalidation');
$check(str_contains($receiver,'hash_equals(')&&str_contains($receiver,'ep_system_state'),'receiver authenticates and deduplicates');
$check(str_contains($receiver,'FOR UPDATE')&&str_contains($receiver,'Store::tx('),'receiver serializes event replay checks');
$mfa=file_get_contents($root.'/app/Application/Security/ManageMfa.php');
$imp=file_get_contents($root.'/app/Application/Security/IssueImpersonation.php');
$domainManager=file_get_contents($root.'/app/Application/Routing/ManageDomain.php');
$revalidate=file_get_contents($root.'/app/Application/Routing/RevalidateDomains.php');
$check(str_contains($app,'ManageMfa::change(')&&str_contains($mfa,'Store::tx('),'MFA write paths layer and transact');
$check(str_contains($app,'IssueImpersonation::issue(')&&str_contains($imp,'status="ACTIVE" FOR UPDATE'),'impersonation grants require active membership');
$consume=file_get_contents($root.'/app/Application/Security/ConsumeImpersonation.php');
$check(str_contains($app,'ConsumeImpersonation::consume(')&&str_contains($app,"requireTenantScope('impersonation.consume'"),'support-token redemption is product-bound and layered');
$check(str_contains($consume,'FOR UPDATE')&&str_contains($consume,'used_at IS NULL')&&str_contains($consume,'Store::tx('),'support token consumed once under transaction');

$check(str_contains($crud,'ManageDomain::edit(')&&str_contains($app,'ManageDomain::activate('),'domain Admin writes layered');
$check(str_contains($domainManager,'CacheInvalidationOutbox::stageForTenant('),'domain mutations stage invalidation');
$check(str_contains($revalidate,'status NOT IN ("SUSPENDED","REMOVED")'),'background domain revalidation excludes inactive records');

$check(str_contains($epaperApp,"/internal/v1/platform/cache-invalidate"),'receiver routes before domain resolution');
$check(strpos($epaperApp,"/internal/v1/platform/cache-invalidate")<strpos($epaperApp,'DomainResolver::resolve()'),'receiver bypasses revoked-domain routing safely');
exit($failed?1:0);
