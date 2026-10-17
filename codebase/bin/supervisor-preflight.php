<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

use EpaperSaas\Services\SupervisorRuntime;

$result=SupervisorRuntime::preflight();
$json=in_array('--json',$argv,true);
if($json){
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
}else{
    echo 'EP-DI Supervisor CLI preflight'.PHP_EOL;
    foreach($result['checks'] as$name=>$check){
        echo (($check['ok']??false)?'[PASS] ':'[FAIL] ').$name.' — '.($check['detail']??'').PHP_EOL;
    }
    echo ($result['ok']?'READY':'ATTENTION').PHP_EOL;
}
exit($result['ok']?0:1);
