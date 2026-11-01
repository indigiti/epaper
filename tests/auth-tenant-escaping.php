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
require_once $root.'/app/Core/Auth.php';
$failure=\EpaperSaas\Core\Auth::loginFailureMessage(new RuntimeException('SERVICE_SCOPE_DENIED',403));
if(!str_contains($failure,'temporarily unavailable')||str_contains($failure,'credentials'))
 throw new RuntimeException('Service scope error is misreported as incorrect password');
$missing=\EpaperSaas\Core\Auth::loginFailureMessage(new RuntimeException('AUTH_TENANT_MEMBERSHIP_REQUIRED',403));
if(!str_contains($missing,'no active access to this publisher'))
 throw new RuntimeException('Missing current-organisation membership is hidden from authenticated users');
$invalid=\EpaperSaas\Core\Auth::loginFailureMessage(new RuntimeException('AUTH_INVALID',401));
if($invalid!=='Unable to sign in with those credentials.')
 throw new RuntimeException('Invalid credentials must retain generic safe error');
$client=\EpaperSaas\Core\Auth::loginFailureMessage(new RuntimeException('AUTH_CLIENT_INVALID',401));
if(!str_contains($client,'temporarily unavailable'))
 throw new RuntimeException('Bad OAuth client misreported as password error');
$limited=\EpaperSaas\Core\Auth::loginFailureMessage(new RuntimeException('AUTH_RATE_LIMITED',429));
if(!str_contains($limited,'Too many sign-in attempts'))
 throw new RuntimeException('Rate-limited login should be distinguishable without revealing account identity');
echo "Publisher tenant login and error classification: OK\n";
