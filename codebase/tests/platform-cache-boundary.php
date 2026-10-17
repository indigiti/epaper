<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$domain=file_get_contents($root.'/app/Services/DomainResolver.php');
$app=file_get_contents($root.'/app/App.php');
$ents=file_get_contents($root.'/app/Services/Entitlements.php');
$access=file_get_contents($root.'/app/Services/PlatformAccess.php');
$failed=[];
$check=static function(bool $yes,string $label)use(&$failed):void{
    echo ($yes?'PASS':'FAIL')." — $label\n";
    if(!$yes)$failed[]=$label;
};
$start=strpos($domain,'public static function resolve(): array');
$end=strpos($domain,'private static function activate(', $start);
$resolve=substr($domain,$start,$end-$start);
$check(str_contains($resolve,"'domain:v2:'"),'domain resolver bypasses historical long-lived cache');
$check(str_contains($resolve,"min(30,"),'domain cache maximum TTL 30 seconds');
$check(!str_contains($resolve,'$fallback&&$host===$canonical'),'canonical default-tenant fail-open fallback removed');
$check(str_contains($domain,"'tenant-meta:v2:'"),'tenant metadata uses bounded new cache namespace');
$check(str_contains($domain,"Publisher tenant is not active"),'inactive tenant response rejected before activation');
$check(str_contains($app,'PlatformAccess::currentState('),'request access uses new bounded authorization policy');
$check(!str_contains($app,"catch(\\Throwable){return'FULL';}"),'Platform network failure cannot return FULL');
$check(str_contains($app,"$"."state==='UNAVAILABLE'?503:402"),'upstream outages return 503 rather than success');
$check(str_contains($ents,"PlatformAccess::currentState()!=='FULL'"),'cached entitlements cannot bypass access check');
$check(substr_count($ents,"PlatformAccess::currentState()!=='FULL'")===3,'all, has and limit deny blocked access');
$check(str_contains($access,"'access:v2:'"),'legacy access cache never trusted');
exit($failed?1:0);
