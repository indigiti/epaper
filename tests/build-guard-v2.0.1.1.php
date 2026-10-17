<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$ok=function(bool $c,string $m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};$read=fn(string$f)=>(string)@file_get_contents($root.'/'.$f);
$sw=$read('public_html/service-worker.js');$vault=$read('public_html/assets/offline-vault.js');
$ok(trim($read('VERSION'))==='2.0.1.1','VERSION 2.0.1.1');
$ok(str_contains($sw,"const VERSION='epdi-v2.0.1.1'"),'Service Worker cache namespace bumped');
$ok(str_contains($sw,'const cacheCopy=response.clone();'),'Response cloned before cache write');
$ok(strpos($sw,'const cacheCopy=response.clone();') < strpos($sw,'await caches.open(SHELL_CACHE)'),'clone occurs before async cache-open boundary');
$ok(!str_contains($sw,'cache.put(request,response.clone())'),'late clone anti-pattern removed');
$ok(str_contains($vault,"const buttons=[...document.querySelectorAll('[data-pwa-install]')];if(!buttons.length)return;e.preventDefault()"),'PWA prompt intercepted only when custom install UI exists');
$ok(!is_file($root.'/database/upgrade-v2.0.1.1.sql'),'no database migration');
if($fails){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$fails)."\n");exit(1);}echo "\nPASS v2.0.1.1 guard\n";
