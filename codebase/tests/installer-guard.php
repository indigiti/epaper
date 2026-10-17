<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/InstallerGuard.php';
require_once dirname(__DIR__).'/app/Core/Config.php';
use EpaperSaas\Core\{InstallerGuard,Config};

$root=sys_get_temp_dir().'/epdi-install-'.bin2hex(random_bytes(8));
@mkdir($root.'/config',0700,true);
@mkdir($root.'/storage',0700,true);
$prevToken=getenv('EPDI_INSTALL_TOKEN');$prevPath=getenv('EPDI_CONFIG_PATH');$prevRuntime=getenv('EPDI_RUNTIME_PATH');
$fails=0;
$check=static function(bool $ok,string $message)use(&$fails):void{if(!$ok){fwrite(STDERR,"FAIL $message\n");$fails++;}};
try{
    putenv('EPDI_INSTALL_TOKEN');putenv('EPDI_CONFIG_PATH');putenv('EPDI_RUNTIME_PATH');
    $check(!InstallerGuard::installed($root),'unconfigured root is not installed');
    $check(!InstallerGuard::authorized($root,'anything'),'no token denies installation');
    putenv('EPDI_INSTALL_TOKEN='.str_repeat('a',20));
    $check(!InstallerGuard::authorized($root,str_repeat('a',20)),'short token denied');
    $valid=str_repeat('T',48);
    putenv('EPDI_INSTALL_TOKEN='.$valid);
    $check(InstallerGuard::authorized($root,$valid),'environment token authorizes');
    $check(!InstallerGuard::authorized($root,str_repeat('B',48)),'wrong token rejected');
    putenv('EPDI_INSTALL_TOKEN');
    file_put_contents($root.'/config/install.token',$valid."\n");
    $check(InstallerGuard::authorized($root,$valid),'private token file authorizes');

    file_put_contents($root.'/config/config.php',"<?php return ['app'=>['timezone'=>'Asia/Kolkata','name'=>'local']];");
    $check(InstallerGuard::installed($root),'existing config blocks reopened installer without lock');
    unlink($root.'/config/config.php');
    file_put_contents($root.'/storage/installed.lock','ok');
    $check(InstallerGuard::installed($root),'installation lock blocks installer');
    unlink($root.'/storage/installed.lock');

    $external=$root.'/external-config.php';
    $overrides=$root.'/external-runtime.php';
    file_put_contents($external,"<?php return ['app'=>['timezone'=>'Asia/Kolkata','name'=>'external'], 'queue'=>['driver'=>'database']];");
    file_put_contents($overrides,"<?php return ['queue'=>['driver'=>'rabbitmq']];");
    putenv('EPDI_CONFIG_PATH='.$external);putenv('EPDI_RUNTIME_PATH='.$overrides);
    $check(InstallerGuard::installed($root),'external config blocks installer');
    Config::load($root);
    $check(Config::get('app.name')==='external','external primary config loaded');
    $check(Config::get('queue.driver')==='rabbitmq','external runtime config merged');
    putenv('EPDI_CONFIG_PATH='.$root.'/missing.php');
    $check(!InstallerGuard::installed($root),'nonexistent external config alone is not installed');
}finally{
    foreach(['EPDI_INSTALL_TOKEN'=>$prevToken,'EPDI_CONFIG_PATH'=>$prevPath,'EPDI_RUNTIME_PATH'=>$prevRuntime] as $key=>$value)putenv($value===false?$key:$key.'='.$value);
    foreach(['config/install.token','config/config.php','storage/installed.lock','external-config.php','external-runtime.php'] as $file)@unlink($root.'/'.$file);
    @rmdir($root.'/config');@rmdir($root.'/storage');@rmdir($root);
}
if($fails)exit(1);
echo 'PASS installer and external configuration protection (12 cases)'."\n";
