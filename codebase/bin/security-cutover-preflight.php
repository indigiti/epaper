<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/SecurityCutover.php';

use EpaperSaas\Core\SecurityCutover;

$root=dirname(__DIR__);
$config=trim((string)(getenv('EPDI_CONFIG_PATH') ?: '')) ?: $root.'/config/config.php';
$runtime=trim((string)(getenv('EPDI_RUNTIME_PATH') ?: ''));
if($runtime===''&&is_file($root.'/config/runtime.php'))$runtime=$root.'/config/runtime.php';
$result=SecurityCutover::inspect($root,$config,$runtime!==''?$runtime:null);
$json=in_array('--json',$argv,true);
if($json) {
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
} else {
    foreach($result['checks'] as$check){
        echo ($check['ok']?'[PASS] ':'[FAIL] ').$check['name'].' — '.$check['detail'].PHP_EOL;
    }
    echo $result['ok']?'SOURCE CONFIGURATION CHECKS PASSED'.PHP_EOL:'SOURCE CONFIGURATION CHECKS FAILED'.PHP_EOL;
    echo 'Credential rotation, Cloudways backup restore and Git-history remediation MUST be independently attested.'.PHP_EOL;
}
exit($result['ok']?0:1);
