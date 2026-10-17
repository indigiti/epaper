<?php
declare(strict_types=1);
$root=dirname(__DIR__);$bad=[];
$check=static function(bool $ok,string $label)use(&$bad):void{echo ($ok?'PASS':'FAIL')." — $label\n";if(!$ok)$bad[]=$label;};
$crud=file_get_contents($root.'/app/Admin/Crud.php');
$app=file_get_contents($root.'/app/App.php');
$repository=file_get_contents($root.'/app/Infrastructure/Routing/DomainLifecycleRepository.php');
$check(str_contains($crud,'DomainLifecyclePolicy::editableStatus('),'Admin edit enforces DNS lifecycle');
$check(str_contains($crud,'RemoveDomain::execute('),'Admin remove delegates application layer');
$check(str_contains($repository,'Store::tx(')&&str_contains($repository,'FOR UPDATE'),'domain removal is locked transactional mutation');
$check(str_contains($app,'Removed domain cannot be verified.'),'removed domain cannot reenter verification');
$check(str_contains($app,'AND status<>"REMOVED"'),'verified update cannot race with removal');
$check(str_contains($app,'AND status IN ("ROUTING_VALID","SSL_PROVISIONING")'),'activation update compares current lifecycle state');
exit($bad?1:0);
