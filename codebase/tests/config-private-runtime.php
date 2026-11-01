<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/Config.php';
use EpaperSaas\Core\Config;
$dir=sys_get_temp_dir().'/epdi-private-config-test-'.bin2hex(random_bytes(6));
if(!mkdir($dir,0700,true))throw new RuntimeException('Failed to create fixture directory');
$file=$dir.'/config.php';
try {
    file_put_contents($file,"<?php return ['app'=>['timezone'=>'UTC'],'test'=>['private'=>true]];");
    putenv('EPDI_PRIVATE_CONFIG_DIR='.$dir);
    Config::load(dirname(__DIR__));
    if(Config::get('test.private')!==true)throw new RuntimeException('Private config directory was not loaded');
    putenv('EPDI_PRIVATE_CONFIG_DIR='.dirname(__DIR__).'/public_html');
    $rejected=false;try{Config::load(dirname(__DIR__));}catch(RuntimeException){$rejected=true;}
    if(!$rejected)throw new RuntimeException('Webroot configuration was accepted');
    echo "ePaper private config fixture passed\n";
}finally{
    putenv('EPDI_PRIVATE_CONFIG_DIR');
    @unlink($file);@rmdir($dir);
}
