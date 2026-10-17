<?php
declare(strict_types=1);

/** Read-only Cloudways staging preflight. Never starts a restore or mutates production. */
$root=require dirname(__DIR__).'/app/bootstrap.php';

use Digiti\Core\{Config,Database,RedisStore};

if(Config::get('app.env')!=='staging'||getenv('EPDI_STAGING_READINESS')!=='allow'){
    fwrite(STDERR,"Readiness preflight denied: a separately configured staging app and EPDI_STAGING_READINESS=allow are required.\n");
    exit(2);
}

$report=['kind'=>'platform_isolated_staging_preflight','at_utc'=>gmdate('c'),
    'source_tests_are_not_live_acceptance'=>true,'production_deploy_approved'=>false,'checks'=>[]];
$record=static function(string $name,bool $ok,string $detail='')use(&$report):void{
    $report['checks'][$name]=['pass'=>$ok,'detail'=>$detail];
};
$stagingHost=trim((string)getenv('EPDI_EXPECTED_PLATFORM_STAGING_HOST'));
$epaperHost=trim((string)getenv('EPDI_EXPECTED_EPAPER_STAGING_HOST'));
$platformDb=trim((string)getenv('EPDI_EXPECTED_PLATFORM_STAGING_DB'));
$epaperDb=trim((string)getenv('EPDI_EXPECTED_EPAPER_STAGING_DB'));
$hostOk=static function(string $actual,string $expected):bool {
    return $actual!==''&&$actual===$expected&&
        (str_contains($actual,'staging')||str_contains($actual,'preview')||str_contains($actual,'stage-'));
};
$platformUrl=parse_url((string)Config::get('app.url',''));
$remoteUrl=parse_url((string)Config::get('integrations.epaper_invalidation_url',''));
$record('staging_application_host',
    is_array($platformUrl)&&($platformUrl['scheme']??'')==='https'&&$hostOk((string)($platformUrl['host']??''),$stagingHost),
    'Operator-provided Platform staging host must match private config and use HTTPS');
$record('staging_epaper_invalidation_origin',
    is_array($remoteUrl)&&($remoteUrl['scheme']??'')==='https'&&
    $hostOk((string)($remoteUrl['host']??''),$epaperHost)&&
    ($remoteUrl['path']??'')==='/internal/v1/platform/cache-invalidate',
    'Fixed HTTPS receiver must be an explicitly named staging ePaper host');
$dbName=(string)Config::get('db.name','');
$record('separate_staging_databases',
    $platformDb!==''&&$epaperDb!==''&&$platformDb!==$epaperDb&&$platformDb===$dbName,
    'Both staging database names must be supplied and differ; this is a configuration check, not a restore test');
$record('configured_invalidation_key',
    strlen((string)Config::get('integrations.epaper_invalidation_secret',''))>=32,
    'Secret presence only; no key material or fingerprint is displayed');
try{
    $pdo=Database::pdo();
    $found=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('schema_migrations','outbox_events','product_tenants','subscriptions')")->fetchColumn();
    $record('platform_mariadb_schema_readable',(int)$found===4,'Four required control-plane tables readable');
}catch(\Throwable){
    $record('platform_mariadb_schema_readable',false,'Staging database connection or required tables unavailable');
}
if(RedisStore::enabled()){
    try{
        $redis=RedisStore::client();
        $record('platform_redis_connectivity',$redis!==null&&$redis->ping()!==false,'Configured staging Redis must respond to PING');
    }catch(\Throwable){$record('platform_redis_connectivity',false,'Configured staging Redis unavailable');}
}else{
    $record('platform_redis_connectivity',false,'Redis is disabled or PHP Redis extension is not installed');
}
$report['all_machine_checks_pass']=!in_array(false,array_column($report['checks'],'pass'),true);
$report['manual_gates_pending']=['separate Platform DB/media backup and isolated restore',
    'private config migration and credential rotation/history remediation',
    'ePaper endpoint/WAF and worker ACK probe',
    'Reader/PDF/clipping/two-tenant/browser/CDN tests',
    'release bundle, rollback, secrets and DigiOps approval'];
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
exit($report['all_machine_checks_pass']?0:1);
