<?php
declare(strict_types=1);
$root=require dirname(__DIR__).'/app/bootstrap.php';

use EpaperSaas\Core\{Config,RedisStore,RequestContext,Store,Uuid};
use EpaperSaas\Services\{CancelledJobException,EditionOperations,JobExecutor,PermanentJobException,ProcessingPipeline,QueueService,RabbitMq,RetryableJobException};

$lane='';$once=false;$maxJobs=max(1,(int)Config::get('queue.worker_max_jobs',250));
foreach(array_slice($argv,1) as$a){if(str_starts_with($a,'--lane='))$lane=substr($a,7);if(str_starts_with($a,'--max-jobs='))$maxJobs=max(1,(int)substr($a,11));if($a==='--once')$once=true;}
if(!$lane||!in_array($lane,['render','assets','ocr','finalize'],true)){fwrite(STDERR,"Usage: php bin/rabbit-worker.php --lane=render|assets|ocr|finalize [--once] [--max-jobs=250]\n");exit(2);}
if(!QueueService::rabbitEnabled()){fwrite(STDERR,"queue.driver is not rabbitmq\n");exit(2);}
$rabbit=RabbitMq::instance();
try{$ch=$rabbit->channel();}catch(Throwable$e){fwrite(STDERR,"RabbitMQ connection failed: {$e->getMessage()}\n");exit(1);}
$queue=$rabbit->queueForLane($lane);$worker=(gethostname()?:'host').':'.$lane.':'.getmypid();$stop=false;$heartbeatSender=null;
$version=trim((string)@file_get_contents(dirname(__DIR__).'/VERSION'))?:'unknown';$lastRedisTouch=0;$lastDbTouch=0;
if(class_exists('PhpAmqpLib\\Connection\\Heartbeat\\PCNTLHeartbeatSender')&&function_exists('pcntl_signal')){try{$hbClass='PhpAmqpLib\\Connection\\Heartbeat\\PCNTLHeartbeatSender';$heartbeatSender=new $hbClass($rabbit->connection());$heartbeatSender->register();}catch(Throwable$e){fwrite(STDERR,"Rabbit heartbeat sender unavailable: {$e->getMessage()}\n");$heartbeatSender=null;}}
if(function_exists('pcntl_async_signals')&&function_exists('pcntl_signal')){pcntl_async_signals(true);pcntl_signal(SIGTERM,function()use(&$stop){$stop=true;});pcntl_signal(SIGINT,function()use(&$stop){$stop=true;});}
$touch=function()use($worker,$lane,$version,&$lastRedisTouch,&$lastDbTouch){$now=time();$payload=['worker'=>$worker,'lane'=>$lane,'pid'=>getmypid(),'version'=>$version,'at'=>gmdate('c')];if($now-$lastRedisTouch>=10){$lastRedisTouch=$now;try{RedisStore::setJson('worker:heartbeat:v2:'.$lane.':'.hash('sha256',$worker),$payload,60);}catch(Throwable){}}if($now-$lastDbTouch<20)return;$lastDbTouch=$now;try{Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES(?,?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',['queue.worker.'.$lane.'.'.$worker,json_encode($payload,JSON_UNESCAPED_SLASHES)]);}catch(Throwable){}};
$delivery=function(string$outcome,array$extra=[])use($worker,$lane,$version){try{Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES(?,?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',['queue.delivery.'.$lane,json_encode(['outcome'=>$outcome,'worker'=>$worker,'lane'=>$lane,'version'=>$version,'at'=>gmdate('c')]+$extra,JSON_UNESCAPED_SLASHES)]);}catch(Throwable){}};
$touch();$ch->basic_qos(null,1,null);$processed=0;
$callback=function($msg)use($worker,$lane,$touch,$delivery,&$processed,$once,$maxJobs,&$stop){
    $touch();$data=json_decode((string)$msg->getBody(),true);$probeGuid=is_array($data)?(string)($data['probe_guid']??''):'';
    if($probeGuid){try{if(!Uuid::isValid($probeGuid))throw new RuntimeException('invalid probe');Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES(?,?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',['queue.probe.'.$probeGuid.'.'.$lane,json_encode(['probe_guid'=>$probeGuid,'lane'=>$lane,'worker'=>$worker,'pid'=>getmypid(),'at'=>gmdate('c')],JSON_UNESCAPED_SLASHES)]);$delivery('PROBE_ACK',['probe_guid'=>$probeGuid]);}catch(Throwable){}$msg->ack();$processed++;if($once||$processed>=$maxJobs)$stop=true;return;}

    $jobGuid=is_array($data)?(string)($data['job_guid']??''):'';$messageTenantGuid=is_array($data)?(string)($data['tenant_guid']??''):'';
    if(!Uuid::isValid($jobGuid)||!Uuid::isValid($messageTenantGuid)){$delivery('INVALID_MESSAGE');$msg->ack();return;}
    try{$messageTenantBin=Uuid::toBinary($messageTenantGuid);}catch(Throwable){$delivery('INVALID_TENANT',['job_guid'=>$jobGuid]);$msg->ack();return;}

    try{
        $result=JobExecutor::run($messageTenantGuid,$jobGuid,$worker,$lane);
        $outcome=$result['outcome'];$delivery($outcome,['job_guid'=>$jobGuid]);
        if(in_array($outcome,['BUSY','NOT_DUE'],true)){usleep(250000);$msg->reject(true);}
        elseif($outcome==='LANE_MISMATCH')$msg->reject(false);
        else $msg->ack();
    }catch(\Throwable $e){$delivery('EXECUTION_ERROR',['job_guid'=>$jobGuid,'error'=>substr($e->getMessage(),0,240)]);usleep(250000);$msg->reject(true);}
    $processed++;if($once||$processed>=$maxJobs)$stop=true;

};
$ch->basic_consume($queue,'',false,false,false,false,$callback);
echo "ePaper SaaS RabbitMQ worker started lane=$lane queue=$queue worker=$worker version=$version max_jobs=$maxJobs\n";
while(!$stop&&$ch->is_consuming()){
    try{$ch->wait(null,false,5);}catch(\PhpAmqpLib\Exception\AMQPTimeoutException){$touch();}
    catch(Throwable$e){fwrite(STDERR,"Rabbit worker error: {$e->getMessage()}\n");sleep(2);break;}
}
if($heartbeatSender){try{$heartbeatSender->unregister();}catch(Throwable){}}
try{$ch->close();}catch(Throwable){}try{$rabbit->close();}catch(Throwable){}
echo "worker stopped; processed=$processed\n";
