<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/SecurityCutover.php';
use EpaperSaas\Core\SecurityCutover;

$tmp=sys_get_temp_dir().'/epdi-security-cutover-'.bin2hex(random_bytes(8));
$root=$tmp.'/checkout';
$private=$tmp.'/private';
mkdir($root.'/config',0700,true);
mkdir($private,0700,true);
$conf=$private.'/config.php';$run=$private.'/runtime.php';
$valid=[
  'app'=>['environment'=>'production','key'=>str_repeat('a',64),'timezone'=>'Asia/Kolkata'],
  'db'=>['user'=>'test_account','pass'=>'test-only-nonproduction'],
  'redis'=>['enabled'=>true,'password'=>'test-only-nonproduction'],
  'platform'=>['service_token'=>'test-only-nonproduction'],
  'queue'=>['driver'=>'rabbitmq','rabbitmq'=>['username'=>'epdi_worker','password'=>'test-only-nonproduction']]
];
$write=static function(string $file,array $value):void {
    file_put_contents($file,"<?php\nreturn ".var_export($value,true).";\n",LOCK_EX);
    chmod($file,0600);
};
$assert=static function(bool $truth,string $message)use(&$failures):void {
    if(!$truth){fwrite(STDERR,"FAIL: $message\n");$failures=($failures??0)+1;}
};
$has=static function(array $report,string $name):?bool {
    foreach($report['checks'] as $check)if($check['name']===$name)return $check['ok'];
    return null;
};
$failures=0;
try{
    $empty=SecurityCutover::inspect($root,$conf);
    $assert(!$empty['ok'],'missing config rejected');
    $write($conf,$valid);
    $passed=SecurityCutover::inspect($root,$conf);
    $assert($passed['ok'],'private strong external configuration passes');
    $assert($passed['credential_rotation_verified']===false,'does not pretend rotation happened');
    $assert($passed['live_backup_restore_verified']===false,'does not pretend restore happened');
    $assert($passed['git_history_remediation_verified']===false,'does not pretend Git history cleaned');

    $local=$root.'/config/config.php';$write($local,$valid);
    $localReport=SecurityCutover::inspect($root,$local);
    $assert($has($localReport,'primary_config_outside_checkout')===false,'checkout config rejected');

    unlink($local);
    symlink($conf,$local);
    $linked=SecurityCutover::inspect($root,$local);
    $assert($linked['ok'],'symlink pointing outside checkout passes');
    unlink($local);

    chmod($conf,0644);
    $world=SecurityCutover::inspect($root,$conf);
    $assert($has($world,'primary_config_private')===false,'world readable file rejected');
    chmod($conf,0600);
    chmod($conf,0640);
    $group=SecurityCutover::inspect($root,$conf);
    $assert($has($group,'primary_config_private')===false,'group readable file rejected');
    chmod($conf,0600);

    $weak=$valid;$weak['app']['key']='not-a-strong-key';$write($conf,$weak);
    $assert($has(SecurityCutover::inspect($root,$conf),'application_key_strength')===false,'weak key rejected');
    $write($conf,$valid);

    $redis=$valid;$redis['redis']['password']='';$write($conf,$redis);
    $assert($has(SecurityCutover::inspect($root,$conf),'redis_credentials')===false,'missing Redis password rejected');
    $write($conf,$valid);

    $write($run,['queue'=>['rabbitmq'=>['username'=>'guest']]]);
    $report=SecurityCutover::inspect($root,$conf,$run);
    $assert($has($report,'rabbitmq_credentials')===false,'guest worker account rejected');
    unlink($run);
    $assert($has(SecurityCutover::inspect($root,$conf,$run),'runtime_config_exists')===false,'missing configured runtime rejected');

    $assert(!str_contains(json_encode($passed),'test-only-nonproduction'),'report never leaks secret material');
}finally{
    @unlink($run);@unlink($conf);@unlink($root.'/config/config.php');
    @rmdir($root.'/config');@rmdir($root);@rmdir($private);@rmdir($tmp);
}
if($failures)exit(1);
echo "PASS production security cutover preflight (12 checks)\n";
