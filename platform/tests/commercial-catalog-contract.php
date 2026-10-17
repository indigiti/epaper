<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$app=file_get_contents($root.'/app/App.php');
$catalog=file_get_contents($root.'/app/Infrastructure/Commercial/CatalogReadRepository.php');
$failed=[];
$check=static function(bool $ok,string $label)use(&$failed):void{
    echo ($ok?'PASS':'FAIL')." — $label\n";
    if(!$ok)$failed[]=$label;
};
$check(str_contains($app,'CatalogReadRepository::snapshot('),'Admin Commercial delegates to read repository');
$check(str_contains($app,"['prices_by_plan']"),'Admin view consumes grouped plans/prices');
$check(substr_count($catalog,'Store::all(')===3,'catalog uses exactly three DB queries regardless of plan count');
$check(str_contains($catalog,'ORDER BY plan_guid,created_at DESC'),'prices grouped in stable newest-first order');
$start=strpos($app,'private function commercial(');$end=strpos($app,'private function subscriptions(',$start);
$view=substr($app,$start,$end-$start);
$check(!str_contains($view,"FROM prices WHERE plan_guid=?"),'no per-plan SELECT remains in Commercial renderer');
exit($failed?1:0);
