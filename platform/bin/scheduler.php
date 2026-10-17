<?php
$root=require dirname(__DIR__).'/app/bootstrap.php';
use Digiti\Core\{RedisStore,Store,Uuid};
$lockKey='scheduler:lock';$redis=RedisStore::client();$locked=false;if($redis){$locked=$redis->set(RedisStore::key($lockKey),(string)getmypid(),['nx','ex'=>55]);if(!$locked){echo "Scheduler already running.\n";exit(0);}}
try{
  $tasks=[['domains','domain.revalidate',['scope'=>'all']],['payments','payment.reconcile',['scope'=>'processing']],['maintenance','auth.cleanup',['older_than'=>'2 minutes']]];
  foreach($tasks as[$queue,$type,$payload]){Store::exec('INSERT INTO jobs(job_guid,queue_name,job_type,payload,status,available_at,created_at) VALUES(?,?,?,?,"QUEUED",NOW(6),NOW(6))',[Uuid::toBinary(Uuid::v7()),$queue,$type,json_encode($payload)]);}
  Store::exec('DELETE FROM auth_codes WHERE expires_at<DATE_SUB(NOW(),INTERVAL 1 DAY)');
  file_put_contents($root.'/storage/scheduler-heartbeat.json',json_encode(['at'=>gmdate('c')]),LOCK_EX);echo "Scheduler dispatched tasks.\n";
}finally{if($redis&&$locked)$redis->del(RedisStore::key($lockKey));}
