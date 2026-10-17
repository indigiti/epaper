<?php
$root=require dirname(__DIR__).'/app/bootstrap.php';
use Digiti\Core\{RedisStore,Store};
$out=['version'=>trim(file_get_contents($root.'/VERSION')),'php'=>PHP_VERSION,'database'=>false,'redis'=>null,'storage_writable'=>is_writable($root.'/storage'),'worker_heartbeat'=>null,'scheduler_heartbeat'=>null];
try{$out['database']=(int)Store::value('SELECT 1')===1;}catch(Throwable$e){$out['database_error']=$e->getMessage();}
if(RedisStore::enabled())try{$out['redis']=(bool)RedisStore::client()?->ping();}catch(Throwable$e){$out['redis']=false;$out['redis_error']=$e->getMessage();}
foreach(['worker','scheduler']as$k){$f=$root.'/storage/'.$k.'-heartbeat.json';if(is_file($f))$out[$k.'_heartbeat']=json_decode(file_get_contents($f),true);}
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";exit($out['database']?0:1);
