<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use EpaperSaas\Core\{Store,Uuid};
use EpaperSaas\Services\{QueueService,JobExecutor};
$once=in_array('--once',$argv,true);$worker=(gethostname()?:'host').':db:'.getmypid();
if(QueueService::rabbitEnabled()){fwrite(STDERR,"Database worker is disabled for RabbitMQ installations. Use direct-recovery-worker.php for requested recovery.\n");exit(2);}
do{
    $jobs=Store::all('SELECT tenant_guid,job_guid FROM ep_processing_jobs WHERE status IN ("QUEUED","RETRY_WAIT","RUNNING") AND (status="RUNNING" OR available_at IS NULL OR available_at<=NOW(6)) ORDER BY created_at,job_guid LIMIT 20');$processed=false;
    foreach($jobs as$j){try{$result=JobExecutor::run(Uuid::fromBinary($j['tenant_guid']),Uuid::fromBinary($j['job_guid']),$worker);if(!in_array($result['outcome'],['BUSY','NOT_DUE'],true)){$processed=true;break;}}catch(\Throwable $e){fwrite(STDERR,$e->getMessage()."\n");}}
    if($once)break;if(!$processed)sleep(2);
}while(true);
