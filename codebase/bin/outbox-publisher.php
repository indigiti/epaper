<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use EpaperSaas\Core\{RedisStore,Store};
use EpaperSaas\Services\{OutboxPublisher,QueueService,RabbitMq};
$once=in_array('--once',$argv,true);$stop=false;$worker=(gethostname()?:'host').':outbox:'.getmypid();
if(!QueueService::rabbitEnabled()){fwrite(STDERR,"queue.driver is not rabbitmq\n");exit(2);}
if(function_exists('pcntl_async_signals')&&function_exists('pcntl_signal')){pcntl_async_signals(true);pcntl_signal(SIGTERM,function()use(&$stop){$stop=true;});pcntl_signal(SIGINT,function()use(&$stop){$stop=true;});}
try{RabbitMq::instance()->connect();}catch(Throwable$e){fwrite(STDERR,"RabbitMQ connection failed: {$e->getMessage()}\n");exit(1);}
$pub=new OutboxPublisher();$lastRedisTouch=0;$lastDbTouch=0;$version=trim((string)@file_get_contents(dirname(__DIR__).'/VERSION'))?:'unknown';echo "ePaper SaaS outbox publisher started $worker version=$version\n";
do{
    try{
        $r=$pub->publishBatch(100);$now=time();$payload=['worker'=>$worker,'lane'=>'outbox','pid'=>getmypid(),'version'=>$version,'result'=>$r,'at'=>gmdate('c')];
        // Idle publishers previously wrote Redis roughly twice per second. A 10-second
        // heartbeat is sufficient for a 60-second TTL and dramatically lowers cache churn.
        if($now-$lastRedisTouch>=10){$lastRedisTouch=$now;RedisStore::setJson('worker:heartbeat:v2:outbox:'.hash('sha256',$worker),$payload,60);}
        if($now-$lastDbTouch>=20){$lastDbTouch=$now;Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES("queue.outbox_worker",?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',[json_encode($payload,JSON_UNESCAPED_SLASHES)]);}
        if(($r['published']+$r['failed']+$r['skipped'])===0)usleep(500000);
    }catch(Throwable$e){fwrite(STDERR,"outbox error: {$e->getMessage()}\n");sleep(2);}
    if($once)break;
}while(!$stop);
RabbitMq::instance()->close();
