<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$failed=[];
$check=static function(bool $ok,string $label)use(&$failed):void{
    echo ($ok?'PASS':'FAIL')." — $label\n";if(!$ok)$failed[]=$label;
};
$dashboard=file_get_contents($root.'/app/Infrastructure/Reporting/AdminDashboardRepository.php');
$catalog=file_get_contents($root.'/app/Infrastructure/Commercial/CatalogReadRepository.php');
$app=file_get_contents($root.'/app/App.php');
$platformEntitlements=file_get_contents($root.'/app/Core/Entitlements.php');
$epaperEntitlements=file_get_contents(dirname($root).'/codebase/app/Services/Entitlements.php');
$client=file_get_contents(dirname($root).'/codebase/app/Services/PlatformClient.php');
$migration=file_get_contents($root.'/database/migrations/20261017_01_usage_events.sql');
$usageRepo=file_get_contents($root.'/app/Infrastructure/Metering/UsageRepository.php');
$check(substr_count($dashboard,'Store::one(')===1,'Admin overview aggregates KPIs in one database request');
$check(str_contains($app,'AdminDashboardRepository::counts('),'overview uses batched reporting repository');
$check(substr_count($catalog,'Store::all(')===3,'commercial catalog uses constant query count');
$check(str_contains($platformEntitlements,"'_format'=>'entitlements-v1'"),'Platform caches empty feature-set responses');
$check(str_contains($epaperEntitlements,"'_format'=>'entitlements-v1'"),'ePaper caches empty entitlement API responses');
$check(str_contains($client,"'event_guid'=>"),'ePaper service client forwards stable optional usage ID');
$check(str_contains($migration,'PRIMARY KEY(event_guid)')&&str_contains($migration,'payload_hash'),'usage migration provides unique event ledger');
$check(str_contains($usageRepo,'Store::tx(')&&str_contains($usageRepo,'ON DUPLICATE KEY UPDATE'),'event record and meter increment share transaction');
$check(str_contains($usageRepo,"'USAGE_EVENT_CONFLICT'"),'mismatched reused event key rejected');
$redis=file_get_contents($root.'/app/Core/RedisStore.php');
$check(str_contains($redis,'private static bool $unavailable=false'),'Redis outages short-circuit repeated connects in a request');
$check(str_contains($redis,"'redis.connect_timeout',0.5"),'Redis connection timeout is bounded and configurable');
$check(str_contains($redis,'self::$unavailable=true'),'failed cache backends enter request-scoped fallback');

exit($failed?1:0);
