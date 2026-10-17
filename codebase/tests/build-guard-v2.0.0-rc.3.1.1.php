<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$pass=function(bool$ok,string$name)use(&$fails){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fails[]=$name;};$read=fn(string$f):string=>is_file($root.'/'.$f)?(string)file_get_contents($root.'/'.$f):'';
$version=trim($read('VERSION'));$css=$read('public_html/assets/app.css');$sw=$read('public_html/service-worker.js');$app=$read('app/App.php');
$pass($version==='2.0.0-rc.3.1.1','VERSION locked to v2.0.0-rc.3.1.1');
$pass(str_contains($css,'.newsstand-hero-copy::before')&&str_contains($css,'filter:blur(32px)'),'Today hero editorial glow retained');
$pass(str_contains($css,'border:3px solid #fff')&&str_contains($css,'rotate(3deg)'),'Today cover border/swing retained');
$pass(str_contains($css,'.edition-rail{')&&str_contains($css,'scroll-snap-type:x mandatory')&&str_contains($css,'touch-action:pan-x'),'Homepage recent editions remain swipeable');
$pass(str_contains($css,'.public-shell .newsstand{')&&str_contains($css,'grid-auto-flow:column')&&str_contains($css,'grid-auto-columns:minmax(190px,230px)'),'Publication page editions use horizontal newsstand rail');
$pass(str_contains($css,'.public-shell .newsstand>.edition-cover img')&&str_contains($css,'object-fit:contain')&&str_contains($css,'object-position:center top'),'Publication covers preserve newspaper geometry');
$pass(str_contains($css,'grid-auto-columns:minmax(160px,62vw)')&&str_contains($css,'grid-auto-columns:minmax(150px,72vw)'),'Publication rail has responsive mobile card sizing');
$pass(str_contains($css,'.public-shell .newsstand>.edition-cover:focus-visible'),'Publication rail has keyboard focus treatment');
$pass(str_contains($css,'@media(prefers-reduced-motion:reduce)')&&str_contains($css,'.public-shell .newsstand{scroll-behavior:auto!important}'),'Publication rail respects reduced motion');
$pass(str_contains($sw,"const VERSION='epdi-v2.0.0-rc.3.1.1'"),'service worker aligned to rc.3.1.1');
$pass(str_contains($app,'editionCoverUrl($r,\'THUMB\')'),'strict THUMB delivery retained');
$pass(!is_file($root.'/database/upgrade-v2.0.0-rc.3.1.1.sql'),'rc.3.1.1 introduces no database migration');
if($fails){fwrite(STDERR,"\nRC.3.1.1 BUILD GUARD FAILED: ".implode(', ',$fails)."\n");exit(1);}echo"\nALL EP-DI v2.0.0-rc.3.1.1 BUILD GUARDS PASSED\n";
