<?php
declare(strict_types=1);
// Source-contract checks for non-destructive publisher onboarding.
$root=dirname(__DIR__);
$app=file_get_contents($root.'/app/App.php');
$view=file_get_contents($root.'/app/Core/View.php');
$onboarding=file_get_contents($root.'/app/Admin/Onboarding.php');
$checks=[
    'route' => str_contains($app, "Onboarding::index(\$method)"),
    'explicit go-live action' => str_contains($app, "Onboarding::action("),
    'navigation' => str_contains($view, '/admin/onboarding'),
    'CSRF protection' => str_contains($app, "if(\$method==='POST')Http::verifyCsrf();"),
    'transactional provisioning' => str_contains($onboarding,'Store::tx('),
    'no silent activation' => str_contains($onboarding,'confirm_hostname'),
    'SSL evidence confirmation' => str_contains($onboarding,'confirm_ssl'),
    'publisher login confirmation' => str_contains($onboarding,'confirm_login'),
    'tenant isolation confirmation' => str_contains($onboarding,'confirm_isolation'),
    'audit record' => str_contains($onboarding,'domain.onboarding.confirm'),
    'DNS check audit' => str_contains($onboarding,'domain.onboarding.dns_check'),
    'no database migration required' => !str_contains($onboarding,'CREATE TABLE'),
];
foreach ($checks as $name=>$ok) {
    echo ($ok?'PASS':'FAIL')." - ".$name.PHP_EOL;
    if (!$ok) exit(1);
}
