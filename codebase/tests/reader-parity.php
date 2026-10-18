<?php
declare(strict_types=1);
require_once __DIR__.'/support/app-source.php';
$root=dirname(__DIR__);$js=(string)file_get_contents($root.'/public_html/assets/reader.js');$css=(string)file_get_contents($root.'/public_html/assets/reader.css');$app=epdiAppSource($root);$fail=[];$t=function(bool $ok,string $n)use(&$fail){echo($ok?'PASS':'FAIL')." — {$n}\n";if(!$ok)$fail[]=$n;};
foreach(['fitPageScale','fitWidthScale','scaleLimits','zoomAt','normalizePan','pointers=new Map','pinch={','setClipMode','data-clip-reselect','data-clip-cancel','gotoPage','prefetchAdjacent','history.pushState','popstate','saveProgress'] as$x)$t(str_contains($js,$x),'Reader JS '.$x);
foreach(['reader-shell','reader-stage','reader-rail','reader-pages','clip-selection','reader-clip-actions','article-modal'] as$x)$t(str_contains($css,$x),'Reader CSS '.$x);
foreach(['data-default-fit','data-scroll-zoom-default','data-clip-visibility','data-reader-prev','data-reader-next','data-reader-page-link'] as$x)$t(str_contains($app,$x),'Reader SSR hook '.$x);
$t(str_contains($js,'fit*.6'),'fit-relative minimum zoom');$t(str_contains($js,"e.touches.length>1")||str_contains($js,'pointers.size===2'),'two-finger pinch handling');$t(str_contains($js,"e.key==='Escape'")&&str_contains($js,'setClipMode(false)'),'Esc cancels clip mode');
$t(str_contains($css,'.reader-shell.reading-active .reader-header{opacity:1!important}'),'Reader header persists while idle');$t(str_contains($css,'.reader-shell.reading-active .reader-pager{opacity:1!important}'),'Reader pager persists while idle');exit($fail?1:0);
