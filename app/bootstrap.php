<?php
$root=dirname(__DIR__);
// Composer dependencies (RabbitMQ client) are optional until queue.driver=rabbitmq.
if(is_file($root.'/vendor/autoload.php')) require_once $root.'/vendor/autoload.php';
spl_autoload_register(function(string $class)use($root){$prefix='EpaperSaas\\';if(!str_starts_with($class,$prefix))return;$rel=str_replace('\\','/',substr($class,strlen($prefix))).'.php';$file=$root.'/app/'.$rel;if(is_file($file))require$file;});
\EpaperSaas\Core\Config::load($root);
\EpaperSaas\Core\Http::securityHeaders();
\EpaperSaas\Core\Http::requestGuid();
\EpaperSaas\Core\Http::correlationGuid();
\EpaperSaas\Services\PerformanceTelemetry::begin();
\EpaperSaas\Services\ErrorCenter::register();
return$root;
