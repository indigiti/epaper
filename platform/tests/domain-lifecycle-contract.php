<?php
declare(strict_types=1);
$root=dirname(__DIR__);$bad=[];
$check=static function(bool $ok,string $label)use(&$bad):void{echo ($ok?'PASS':'FAIL')." — $label\n";if(!$ok)$bad[]=$label;};
$crud=file_get_contents($root.'/app/Admin/Crud.php');
$app=file_get_contents($root.'/app/App.php');
$usecase=file_get_contents($root.'/app/Application/Routing/ManageDomain.php');
$repository=file_get_contents($root.'/app/Infrastructure/Routing/DomainLifecycleRepository.php');
$check(str_contains($crud,'ManageDomain::edit('),'Admin domain edit delegates application service');
$check(str_contains($crud,'RemoveDomain::execute('),'Admin remove delegates application service');
$check(str_contains($app,'ManageDomain::verify(')&&str_contains($app,'ManageDomain::activate('),'Verify/Activate HTTP presenters are thin');
$check(str_contains($usecase,'DomainLifecyclePolicy::editableStatus('),'edit enforces domain lifecycle policy');
$check(str_contains($repository,'Store::tx(')&&str_contains($repository,'FOR UPDATE'),'domain removal is locked transactional mutation');
$check(str_contains($usecase,"'REMOVED','SUSPENDED'"),'removed/suspended domain cannot be verified');
$check(str_contains($usecase,'AND status=?'),'verification update cannot race with status changes');
$check(str_contains($usecase,'AND status IN ("ROUTING_VALID","SSL_PROVISIONING")'),'activation compares fresh lifecycle status');
$check(substr_count($usecase,'CacheInvalidationOutbox::stageForTenant(')===3,'edit/verify/activate stage signed invalidations');
exit($bad?1:0);
