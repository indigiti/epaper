<?php
declare(strict_types=1);
$root=dirname(__DIR__);$epaper=dirname($root).'/codebase/app/Services/PlatformClient.php';
$platform=$root.'/app/App.php';
$fail=[];
$check=static function(bool $ok,string $name)use(&$fail):void{echo ($ok?'PASS':'FAIL')." — ".$name."\n";if(!$ok)$fail[]=$name;};
$check(is_file($epaper),'ePaper PHP service adapter exists');
if(!is_file($epaper))exit(1);
$client=file_get_contents($epaper);$server=file_get_contents($platform);
foreach([
    '/internal/v1/auth/direct',
    '/internal/v1/auth/token',
    '/internal/v1/domains/resolve',
    '/internal/v1/memberships/verify',
    '/internal/v1/usage',
    '/internal/v1/heartbeats',
    '/internal/v1/audit',
    '/internal/v1/errors'
] as $path) {
    $check(str_contains($server,$path)&&str_contains($client,$path),"both applications implement $path");
}
foreach(['tenant','access','entitlements'] as $part) {
    $check(str_contains($client,"/'.$part")||str_contains($client,"/internal/v1/tenants/"),"ePaper implements $part tenant request");
    $check(str_contains($server,$part),'Platform implements tenant operation '.$part);
}
$check(str_contains($client,'X-Digiti-Service-Token')&&str_contains($server,"auth.direct"),'service-token auth contract retained');
$check(str_contains($client,'product_tenant_guid')&&str_contains($server,'product_tenant_guid'),'tenant field name retained');
$check(str_contains($client,'code_verifier')&&str_contains($server,'code_verifier'),'OAuth PKCE field retained');
exit($fail?1:0);
