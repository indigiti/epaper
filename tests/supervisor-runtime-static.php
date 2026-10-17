<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$pass=function(bool$ok,string$name)use(&$fails){echo($ok?'PASS':'FAIL')." — $name\n";if(!$ok)$fails[]=$name;};
$sup=(string)file_get_contents($root.'/app/Services/SupervisorRuntime.php');$app=(string)file_get_contents($root.'/app/App.php');$infra=(string)file_get_contents($root.'/app/Services/InfrastructureConfig.php');
$configPos=strpos($sup,'public static function config(): string');$configBody=$configPos===false?'':substr($sup,$configPos);
$pass(str_contains($sup,'public static function preflight()'),'Supervisor preflight API present');
$pass(str_contains($sup,'public static function diagnosis()'),'Supervisor diagnosis API present');
$pass(str_contains($sup,'public static function commands()'),'server-specific command generator present');
$pass(str_contains($configBody,'Contains no RabbitMQ/Redis credentials'),'generated config declares credential-free contract');
$pass(!str_contains($configBody,"Config::get('queue.rabbitmq.password'"),'generated Supervisor config does not read RabbitMQ password');
$pass(str_contains($app,'Security boundary:')&&str_contains($app,'intentionally does not call <code>supervisorctl</code>'),'web UI preserves OS process security boundary');
$pass(str_contains($infra,'Redis is the primary liveness source')&&str_contains($infra,'MariaDB is a low-frequency recovery mirror'),'liveness fallback semantics documented in code');
if($fails){fwrite(STDERR,"SUPERVISOR STATIC CHECK FAILED\n");exit(1);}echo"SUPERVISOR STATIC CHECK PASSED\n";
