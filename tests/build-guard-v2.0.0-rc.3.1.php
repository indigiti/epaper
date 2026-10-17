<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$pass=function(bool$ok,string$name)use(&$fails){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fails[]=$name;};$read=fn(string$f):string=>is_file($root.'/'.$f)?(string)file_get_contents($root.'/'.$f):'';
$version=trim($read('VERSION'));$css=$read('public_html/assets/app.css');$sw=$read('public_html/service-worker.js');$app=$read('app/App.php');
$pass($version==='2.0.0-rc.3.1','VERSION locked to v2.0.0-rc.3.1');
$pass(str_contains($css,'.newsstand-hero-copy::before')&&str_contains($css,'filter:blur(32px)'),'Today hero has restrained blurred editorial glow');
$pass(str_contains($css,'border:3px solid #fff')&&str_contains($css,'rotate(3deg)')&&str_contains($css,'transform-origin:56% 14%'),'Today cover has white paper border and 3-degree hover swing');
$pass(str_contains($css,'.newsstand-hero-cover:focus-visible img'),'hero cover motion/focus interaction is keyboard-addressable');
$pass(str_contains($css,'.edition-rail{')&&str_contains($css,'scroll-snap-type:x mandatory')&&str_contains($css,'touch-action:pan-x'),'Recent editions remain horizontal/swipeable with snap');
$pass(str_contains($css,'.edition-tile-cover img')&&str_contains($css,'object-fit:contain'),'Recent-edition covers preserve newspaper aspect without stretching');
$pass(str_contains($css,'.archive-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr))'),'Archive is three-column at desktop');
$pass(str_contains($css,'@media(max-width:1100px){.archive-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}'),'Archive is two-column at tablet/laptop breakpoint');
$pass(str_contains($css,'@media(max-width:700px)')&&str_contains($css,'.archive-grid{grid-template-columns:1fr}'),'Archive is single-column on mobile');
$pass(str_contains($css,'.archive-cover img')&&substr_count($css,'object-fit:contain')>=3,'Archive/hero/newsstand cover surfaces avoid image distortion');
$pass(str_contains($css,'@media(prefers-reduced-motion:reduce)')&&str_contains($css,'.newsstand-hero-cover:focus-visible img'),'reduced-motion guard retained');
$pass(str_contains($sw,"const VERSION='epdi-v2.0.0-rc.3.1'"),'service worker aligned to rc.3.1');
$pass(str_contains($app,'editionCoverUrl($r,\'THUMB\')'),'strict thumbnail public-list delivery retained');
$pass(!is_file($root.'/database/upgrade-v2.0.0-rc.3.1.sql'),'rc.3.1 introduces no database migration');
if($fails){fwrite(STDERR,"\nRC.3.1 BUILD GUARD FAILED: ".implode(', ',$fails)."\n");exit(1);}echo"\nALL EP-DI v2.0.0-rc.3.1 BUILD GUARDS PASSED\n";
