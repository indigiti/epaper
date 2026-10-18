<?php
declare(strict_types=1);
require_once __DIR__.'/support/app-source.php';
$root=dirname(__DIR__);$fails=[];$ok=function(bool$c,string$m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};$app=epdiAppSource($root);$cache=(string)file_get_contents($root.'/app/Services/PublicReadCache.php');$css=(string)file_get_contents($root.'/public_html/assets/app.css');
$ok(substr_count($app,'editionCoverUrl($r,\'THUMB\')')>=3,'home/archive/publication cover lists request THUMB');
$ok(str_contains($app,'archiveCalendar($monthStart,$monthEnd,$publication,$city,$language)'),'Archive calendar respects active public filters');
$ok(str_contains($app,'$range===\'7\'')&&str_contains($app,'Last 7 days'),'Archive retains 7-day quick view');
$ok(str_contains($app,'Today</a>')&&str_contains($app,'Yesterday</a>'),'Archive quick date shortcuts present');
$ok(str_contains($app,'archive-day-empty')&&str_contains($app,'aria-disabled="true"'),'Unavailable dates are represented without repetitive copy or fake links');
$ok(str_contains($app,'aria-current="date"'),'Selected archive date has semantic current state');
$ok(str_contains($app,'http_build_query($clean')&&str_contains($app,"'publication','city','language','year','date','range','month'"),'Archive links preserve supported filter/query state');
$ok(str_contains($cache,"'public:archive-calendar:r'")&&str_contains($cache,'MultiLayerCache::remember'),'Archive calendar participates in multi-layer cache');
$ok(str_contains($css,'scroll-snap-type:x proximity'),'date/edition rails are touch-friendly horizontal surfaces');
$ok(str_contains($css,'.public-shell .newsstand{')&&str_contains($css,'grid-auto-flow:column')&&str_contains($css,'scroll-snap-type:x mandatory'),'Publication edition list is a horizontal swipe rail');
$ok(str_contains($css,'.public-shell .newsstand>.edition-cover img')&&str_contains($css,'object-fit:contain'),'Publication covers do not stretch/crop newspaper pages');
$ok(str_contains($css,'.archive-day.unavailable'),'unavailable date styling packaged');
$ok(str_contains($css,'.cover-missing'),'lightweight thumbnail-missing placeholder packaged');
if($fails){fwrite(STDERR,"\nPUBLIC NEWSSTAND/ARCHIVE STATIC GUARD FAILED\n - ".implode("\n - ",$fails)."\n");exit(1);}echo"\nPASS public newsstand/archive static guard\n";
