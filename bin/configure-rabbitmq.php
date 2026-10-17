<?php
declare(strict_types=1);
$root=dirname(__DIR__);$file=$root.'/config/config.php';
if(!is_file($file)){fwrite(STDERR,"config/config.php not found\n");exit(2);} $cfg=require$file;if(!is_array($cfg)){fwrite(STDERR,"Invalid config\n");exit(2);} 
$args=[];foreach(array_slice($argv,1)as$a){if(str_contains($a,'=')){[$k,$v]=explode('=',$a,2);$args[ltrim($k,'-')]=$v;}}
$driver=strtolower((string)($args['driver']??'rabbitmq'));if(!in_array($driver,['rabbitmq','database'],true)){fwrite(STDERR,"--driver must be rabbitmq or database\n");exit(2);} 
$existing=$cfg['queue']['rabbitmq']??[];$host=(string)($args['host']??$existing['host']??'127.0.0.1');$port=(int)($args['port']??$existing['port']??5672);$user=(string)($args['user']??$existing['username']??'');$vhost=(string)($args['vhost']??$existing['vhost']??'/');$pass=(string)($existing['password']??'');
if(isset($args['password-env'])){$env=getenv((string)$args['password-env']);if($env===false){fwrite(STDERR,"Password environment variable not set\n");exit(2);}$pass=(string)$env;}elseif(isset($args['password'])){$pass=(string)$args['password'];}elseif($driver==='rabbitmq'&&$pass===''){fwrite(STDERR,"RabbitMQ password is required. Prefer --password-env=ENV_NAME to avoid shell history.\n");exit(2);}
$cfg['queue']=[
 'driver'=>$driver,
 'worker_max_jobs'=>(int)($cfg['queue']['worker_max_jobs']??250),
 'expected_workers'=>$cfg['queue']['expected_workers']??['outbox'=>1,'render'=>1,'assets'=>2,'ocr'=>1,'finalize'=>1],
 'max_attempts'=>['edition_render'=>3,'page_assets'=>3,'page_ocr'=>3,'edition_finalize'=>5],
 'rabbitmq'=>[
  'host'=>$host,'port'=>$port,'username'=>$user,'password'=>$pass,'vhost'=>$vhost,
  'connection_timeout'=>3.0,'read_write_timeout'=>70.0,'heartbeat'=>30,'keepalive'=>true,'confirm_timeout'=>5.0,
  'exchange'=>'epaper.jobs','dead_exchange'=>'epaper.dead','dead_queue'=>'epaper.dead','retry_exchange_prefix'=>'epaper.retry.',
  'queues'=>['render'=>'epaper.render','assets'=>'epaper.assets','ocr'=>'epaper.ocr','finalize'=>'epaper.finalize'],
 ],
];
$tmp=$file.'.tmp.'.getmypid();$body="<?php\nreturn ".var_export($cfg,true).";\n";if(file_put_contents($tmp,$body,LOCK_EX)===false){fwrite(STDERR,"Could not write temp config\n");exit(1);} @chmod($tmp,0640);if(!rename($tmp,$file)){@unlink($tmp);fwrite(STDERR,"Could not replace config file\n");exit(1);}echo "Queue driver configured: $driver\n";if($driver==='rabbitmq')echo "RabbitMQ: $host:$port vhost=$vhost user=$user\nRun: php bin/queue-diagnostic.php\n";
