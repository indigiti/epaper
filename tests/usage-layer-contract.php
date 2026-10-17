<?php
declare(strict_types=1);
$root=dirname(__DIR__);$failed=[];
$check=static function(bool $ok,string $name)use(&$failed):void{
    echo ($ok?'PASS':'FAIL')." — ".$name."\n";if(!$ok)$failed[]=$name;
};
$app=file_get_contents($root.'/app/App.php');
$usecase=file_get_contents($root.'/app/Application/Metering/RecordUsage.php');
$repo=file_get_contents($root.'/app/Infrastructure/Metering/UsageRepository.php');
$check(str_contains($app,'RecordUsage::execute('),'HTTP endpoint delegates application use case');
$check(str_contains($app,"requireTenantScope('usage.write'"),'machine scope remains product bound');
$check(str_contains($repo,'ON DUPLICATE KEY UPDATE')&&str_contains($repo,'used_value=GREATEST(0,used_value+CAST('),'atomic metering UPSERT');
$check(!str_contains($repo,'SELECT usage_guid FROM usage_meters'),'no race-prone read-before-write counter');
$check(str_contains($repo,'f.product_guid IS NULL OR f.product_guid=pt.product_guid'),'product-specific feature boundaries');
$check(str_contains($usecase,"RedisStore::del('entitlements:"),'meter update invalidates Platform entitlement cache');
exit($failed?1:0);
