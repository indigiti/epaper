<?php
declare(strict_types=1);
$r=dirname(__DIR__);$f=[];$p=function(bool $ok,string $n)use(&$f){echo($ok?'PASS':'FAIL')." — $n\n";if(!$ok)$f[]=$n;};
$p(trim((string)file_get_contents($r.'/VERSION'))==='1.1.9.9','version');
$v=(string)file_get_contents($r.'/app/Core/View.php');$c=(string)file_get_contents($r.'/public_html/assets/auth-v1.1.9.9.css');$sw=(string)file_get_contents($r.'/public_html/service-worker.js');
$p(str_contains($v,'auth-fit-v1199')&&str_contains($v,'data-auth-version="1.1.9.9"'),'v1.1.9.9 auth markup');
$p(str_contains($v,'/assets/auth-v1.1.9.9.css?v=1.1.9.9'),'auth stylesheet cache bust');
$p(str_contains($c,'grid-template-rows:max-content max-content')&&str_contains($c,'height:max-content!important'),'content shrink wrap');
$p(str_contains($c,'padding:46px 28px 20px')&&str_contains($c,'margin:11px 0 0!important'),'compact vertical spacing');
$p(str_contains($sw,"epaper-saas-v1.1.9.9")&&str_contains($sw,'auth-v1.1.9.9.css?v=1.1.9.9'),'service worker aligned');
$p(!is_file($r.'/app/Services/QrCode.php')&&!is_file($r.'/app/Services/ClipService.php'),'protected QR/clip services not packaged');
if($f){exit(1);}echo "ALL HOTFIX PACKAGE CHECKS PASSED\n";
