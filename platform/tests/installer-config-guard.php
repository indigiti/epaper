<?php
declare(strict_types=1);

// Standalone, read-only regression that simulates a missing installer lock.
// The temporary fixture is outside the checkout; no database or Cloudways
// credentials are loaded, and the installer must exit before processing POST.
$root=dirname(__DIR__);
$fail=[];
$check=static function(bool $ok,string $name)use(&$fail):void{
    echo ($ok?'PASS':'FAIL')." — ".$name.PHP_EOL;
    if(!$ok)$fail[]=$name;
};
$installer=(string)file_get_contents($root.'/public_html/install.php');
$config=(string)file_get_contents($root.'/app/Core/Config.php');
$manifest=json_decode((string)file_get_contents($root.'/manifest.json'),true);
$version=trim((string)file_get_contents($root.'/VERSION'));
$check(is_array($manifest)&&($manifest['version']??null)===$version,'Platform version and manifest are aligned');
$check(str_contains($installer,'is_file($lock)')&&str_contains($installer,'is_file($configFile)'),'Installation guarded by existing configuration and lock');
$check(str_contains($installer,"is_file(\$root.'/config/config.php')"),'Default runtime config also blocks reinstall');
$check(str_contains($config,'DIGITI_PRIVATE_CONFIG_DIR'),'External private configuration loader available');
$check(str_contains($installer,'DIGITI_PRIVATE_CONFIG_DIR'),'Installer inspects private configuration path');
$dir=sys_get_temp_dir().'/platform-installer-guard-'.bin2hex(random_bytes(6));
if(!mkdir($dir,0700,true))throw new RuntimeException('Unable to create test directory');
file_put_contents($dir.'/config.php',"<?php return ['test'=>true];\n");
$simulate=static function(string $target)use($root):array{
    $script='putenv('.var_export('DIGITI_PRIVATE_CONFIG_DIR='.$target,true).');'
           .'$_SERVER["REQUEST_METHOD"]="GET";'
           .'require '.var_export($root.'/public_html/install.php',true).';';
    $process=proc_open([PHP_BINARY,'-r',$script],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Unable to launch safe installer test');
    $out=(string)stream_get_contents($pipes[1]);
    $err=(string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($process),$out,$err];
};
try{
    [$code,$output,$error]=$simulate($dir);
    $check($code===0&&str_contains($output,'Installer locked')&&!str_contains($output,'<form'),'Lost installer lock cannot reinitialize configured Platform');
    [$code,$output,$error]=$simulate($root.'/public_html');
    $check($code===0&&str_contains($output,'Installer unavailable')&&!str_contains($output,'<form'),'Private config inside webroot is rejected');
}finally{
    @unlink($dir.'/config.php');
    @rmdir($dir);
}
if($fail){fwrite(STDERR,implode(PHP_EOL,$fail).PHP_EOL);exit(1);}
echo "Platform installer security regression passed".PHP_EOL;
