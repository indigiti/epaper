<?php
declare(strict_types=1);
// Source-contract checks for non-destructive publisher onboarding.
$root=dirname(__DIR__);
$app=file_get_contents($root.'/app/App.php');
$view=file_get_contents($root.'/app/Core/View.php');
$onboarding=file_get_contents($root.'/app/Admin/Onboarding.php');
$dashboard=file_get_contents($root.'/app/Admin/OnboardingDashboard.php');
$css=file_get_contents($root.'/public_html/assets/onboarding.css');
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
    'legacy activation bypass closed' => str_contains($app,'Retired legacy instant-activation endpoint'),
    'domain-first dashboard' => str_contains($onboarding,'OnboardingDashboard::render('),
    'existing domain inventory' => str_contains($dashboard,'id="existing-domains"'),
    'existing publisher tenants' => str_contains($dashboard,'id="existing-publishers"'),
    'new setup collapsed by default' => str_contains($dashboard,'<details id="new-publisher"'),
    'true application host status' => str_contains($onboarding,'application_url') && str_contains($dashboard,'isCanonical('),
    'progress tied to stored state' => str_contains($dashboard,'Onboarding::state($d)'),
    'onboarding asset fingerprinted' => str_contains($view,"hash_file('sha256'") && str_contains($view,'onboarding.css?v='),
    'responsive onboarding stylesheet' => str_contains($css,'@media(max-width:520px)'),
    'review distinguishes active legacy sites' => str_contains($dashboard,'Active · review'),
    'no database migration required' => !str_contains($onboarding,'CREATE TABLE'),
];
foreach ($checks as $name=>$ok) {
    echo ($ok?'PASS':'FAIL')." - ".$name.PHP_EOL;
    if (!$ok) exit(1);
}
