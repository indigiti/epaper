<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/Config.php';
require_once dirname(__DIR__).'/app/Core/RedisStore.php';
require_once dirname(__DIR__).'/app/Core/RateLimiter.php';
use EpaperSaas\Core\{Config,RateLimiter};
$root=sys_get_temp_dir().'/epdi-rate-limit-'.bin2hex(random_bytes(8));
$dir=$root.'/config';$cache=$root.'/limits';
mkdir($dir,0700,true);
file_put_contents($dir.'/config.php',"<?php return ".var_export([
 'redis'=>['enabled'=>false,'prefix'=>'epdi:test:'],
 'security'=>['rate_limit_storage_dir'=>$cache]
],true).";\n");
Config::load($root);
$cases=0;$fails=0;
$check=static function(bool $ok,string $name)use(&$cases,&$fails):void{
 $cases++;
 if(!$ok){fwrite(STDERR,"FAIL: $name\n");$fails++;}
};
try{
 $bucket='login:192.0.2.1:account';
 $check(RateLimiter::allow($bucket,2,60),'first attempt allowed');
 $check(RateLimiter::allow($bucket,2,60),'second attempt allowed');
 $_SESSION=[];
 $check(!RateLimiter::allow($bucket,2,60),'new PHP session cannot bypass file limit');
 $check(RateLimiter::allow('another',1,60),'independent bucket allowed');
 $check(!RateLimiter::allow('another',1,60),'independent bucket limited');
 $check(!RateLimiter::allow('invalid',0,60),'limit zero denied');
 $check(!RateLimiter::allow('invalid',2,0),'window zero denied');
 $files=glob($cache.'/*.json')?:[];
 $check(count($files)===2,'hashed shared counter files created');
 foreach($files as$file){
  $check((fileperms($file)&0077)===0,'private counter file permissions');
  $contents=json_decode((string)file_get_contents($file),true);
  $contents['until']=time()-7201;
  file_put_contents($file,json_encode($contents),LOCK_EX);
  touch($file,time()-7201);
 }
 $check(RateLimiter::pruneExpired(200)===2,'bounded cleanup removes expired counters');
}finally{
 foreach(glob($cache.'/*.json')?:[] as$file)@unlink($file);
 @rmdir($cache);@unlink($dir.'/config.php');@rmdir($dir);@rmdir($root);
}
if($fails)exit(1);
echo "PASS cross-session file rate limiting ($cases checks)\n";
