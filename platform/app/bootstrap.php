<?php
$root = dirname(__DIR__);
spl_autoload_register(function(string $class) use ($root){
    $prefix='Digiti\\'; if(!str_starts_with($class,$prefix))return;
    $rel=str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    $file=$root.'/app/'.$rel; if(is_file($file))require $file;
});
\Digiti\Core\Config::load($root);
\Digiti\Core\Http::securityHeaders();
\Digiti\Core\Http::requestGuid();
\Digiti\Core\Auth::start();
\Digiti\Core\ErrorCenter::register();
return $root;
