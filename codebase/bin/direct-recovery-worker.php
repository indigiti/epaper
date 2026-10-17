<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
use EpaperSaas\Core\Store;
use EpaperSaas\Services\DirectRecovery;
$once=in_array('--once',$argv,true);$seconds=1800;foreach($argv as$a)if(str_starts_with($a,'--max-seconds='))$seconds=max(10,min(7200,(int)substr($a,14)));
// One runner per installation bounds CPU/memory; normal lane workers may continue under shared job locks.
if((int)Store::value('SELECT GET_LOCK("epdi_direct_runner",0)')!==1)exit(0);
$stop=false;if(function_exists('pcntl_async_signals')){pcntl_async_signals(true);pcntl_signal(SIGTERM,function()use(&$stop){$stop=true;});pcntl_signal(SIGINT,function()use(&$stop){$stop=true;});}
$start=time();
try{do{
    Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES("recovery.worker",?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',[json_encode(['pid'=>getmypid(),'version'=>trim(file_get_contents(dirname(__DIR__).'/VERSION')),'at'=>gmdate('c')])]);
    $count=DirectRecovery::tick();
    if($once&&DirectRecovery::pending()===0)break;
    if(!$count)sleep(2);
}while(!$stop&&(!$once||time()-$start<$seconds));}
finally{Store::value('SELECT RELEASE_LOCK("epdi_direct_runner")');}
