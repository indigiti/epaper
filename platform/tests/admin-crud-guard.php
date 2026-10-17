<?php
$root=dirname(__DIR__);$fail=[];$check=function(bool $ok,string $name)use(&$fail){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fail[]=$name;};
$app=file_get_contents($root.'/app/App.php');$crud=file_get_contents($root.'/app/Admin/Crud.php');$up=file_get_contents($root.'/public_html/update.php');$installer=file_get_contents($root.'/public_html/install.php');
foreach(['/health/live','/health/ready','/internal/v1/domains/resolve','/admin/organisations','/admin/products','/admin/applications','/admin/product-tenants','/admin/domains','/admin/service-accounts','/admin/commercial','/admin/subscriptions','/admin/billing'] as $route)$check(str_contains($app,$route),'baseline route '.$route);
foreach(['/admin/products/([0-9a-f-]{36})/edit','/admin/applications/([0-9a-f-]{36})/edit','/admin/organisations/([0-9a-f-]{36})/edit','/admin/domains/([0-9a-f-]{36})/edit','/admin/service-accounts/([0-9a-f-]{36})/rotate','/admin/subscriptions/([0-9a-f-]{36})/edit'] as $route)$check(str_contains($crud,$route),'CRUD route '.$route);
$check(str_contains($up,'ePaper SaaS'),'existing installation ePaper SaaS rename');
$check(str_contains($installer,'ePaper SaaS'),'fresh install ePaper SaaS seed');
$check(str_contains($installer,'"EPAPER"'),'EPAPER product key preserved');
$check(str_contains($installer,'"city_epaper"'),'city_epaper application key preserved');
$policy=file_get_contents($root.'/app/Domain/Routing/DomainResolutionPolicy.php');$repository=file_get_contents($root.'/app/Infrastructure/Routing/DomainRepository.php');
$check(str_contains($app,'ResolveDomain::execute(')&&str_contains($policy,"'organisation_status'")&&str_contains($policy,"'product_status'")&&str_contains($policy,"'application_status'")&&str_contains($policy,"'tenant_status'")&&str_contains($repository,'JOIN product_tenants')&&str_contains($repository,'JOIN organisations'),'domain resolver parent-status enforcement');
$check(!is_file($root.'/config/config.php'),'release contains no production config');
exit($fail?1:0);
