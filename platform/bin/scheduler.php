<?php
declare(strict_types=1);
$root=require dirname(__DIR__).'/app/bootstrap.php';
use Digiti\Core\{RedisStore,Store,Uuid};
use Digiti\Application\Operations\QueueLeaseRecovery;

$redis=null;$locked=false;$dbLock=false;
try{
    $redis=RedisStore::client();
    if($redis){
        $locked=(bool)$redis->set(RedisStore::key('scheduler:lock'),(string)getmypid(),['nx','ex'=>55]);
        if(!$locked){echo "Scheduler already running.\n";exit(0);}
    }
}catch(\Throwable){$redis=null;}

if(!$redis){
    // Database advisory lock protects against parallel cron when Redis is unavailable.
    $dbLock=(int)Store::value('SELECT GET_LOCK(?,0)',['digiti:platform:scheduler'])===1;
    if(!$dbLock){echo "Scheduler already running.\n";exit(0);}
}
try{
    $recovered=QueueLeaseRecovery::recover();
    $tasks=[
        ['domains','domain.revalidate',['scope'=>'all']],
        ['payments','payment.reconcile',['scope'=>'processing']],
        ['maintenance','auth.cleanup',['older_than'=>'2 minutes']]
    ];
    foreach($tasks as [$queue,$type,$payload]){
        Store::exec('INSERT INTO jobs(job_guid,queue_name,job_type,payload,status,available_at,created_at) VALUES(?,?,?,?,"QUEUED",NOW(6),NOW(6))',[
            Uuid::toBinary(Uuid::v7()),$queue,$type,json_encode($payload,JSON_THROW_ON_ERROR)
        ]);
    }
    Store::exec('DELETE FROM auth_codes WHERE expires_at<DATE_SUB(NOW(),INTERVAL 1 DAY)');
    file_put_contents($root.'/storage/scheduler-heartbeat.json',json_encode(['at'=>gmdate('c')]),LOCK_EX);
    echo "Scheduler dispatched tasks and checked expired leases.\n";
}finally{
    if($redis&&$locked){try{$redis->del(RedisStore::key('scheduler:lock'));}catch(\Throwable){}}
    if($dbLock)Store::value('SELECT RELEASE_LOCK(?)',['digiti:platform:scheduler']);
}
