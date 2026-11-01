<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/Core/View.php';
$unsafe='<img src=x onerror="alert(1)"> & "';
$escaped=\EpaperSaas\Core\View::e($unsafe);
if($escaped!==htmlspecialchars($unsafe,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')||str_contains($escaped,'<img'))
 throw new RuntimeException('Tenant name escaping contract failed');
$auth=(string)file_get_contents($root.'/app/Core/Auth.php');
$from=strpos($auth,'public static function login(');
$to=strpos($auth,'public static function mobileLogin(');
if($from===false||$to===false||$to<=$from)throw new RuntimeException('Login section missing');
$login=substr($auth,$from,$to-$from);
if(!str_contains($login,"<span>'.View::e(\$brand).'</span>")||str_contains($login,"<span>'.\$brand.'</span>"))
 throw new RuntimeException('Unescaped tenant name in publisher login');
echo "Publisher tenant name escaping: OK\n";
