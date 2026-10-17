<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use EpaperSaas\Core\{RedisStore,Store};use EpaperSaas\Services\PlatformClient;
$checks=[];try{Store::value('SELECT 1');$checks['db']=true;}catch(Throwable){$checks['db']=false;}try{$checks['redis']=!RedisStore::enabled()||RedisStore::client()?->ping()!==false;}catch(Throwable){$checks['redis']=false;}try{$checks['platform']=(bool)(PlatformClient::instance()->health()['ok']??false);}catch(Throwable){$checks['platform']=false;}$checks['storage']=is_writable(dirname(__DIR__).'/storage');$ok=!in_array(false,$checks,true);echo json_encode(['ok'=>$ok,'checks'=>$checks],JSON_PRETTY_PRINT)."\n";exit($ok?0:1);
