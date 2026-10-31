<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/Build.php';

use EpaperSaas\Core\Build;
$root=dirname(__DIR__);
$assert=static function(bool $ok,string $label):void{
    if(!$ok)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
};

$release=trim(file_get_contents($root.'/VERSION'));
$assert($release==='2.0.2.3','Release version rotated after immutable HALO asset change');
$css=Build::asset('/assets/home-halo.css');
$js=Build::asset('/assets/home-halo.js');
$assert((bool)preg_match('~^/assets/home-halo\.css\?v=2\.0\.2\.3\.[a-z0-9]+-[a-z0-9]+$~',$css),
    'HALO CSS URL includes on-disk content metadata stamp');
$assert((bool)preg_match('~^/assets/home-halo\.js\?v=2\.0\.2\.3\.[a-z0-9]+-[a-z0-9]+$~',$js),
    'HALO loader URL has independent fingerprint');
$assert($css===Build::asset('/assets/home-halo.css'),'Stamp stable across repeated calls');
$assert(Build::asset('/assets/home-halo.css?mode=test')===
    str_replace('?v=','?mode=test&v=',$css),'Existing asset query strings preserved');

$cssStat=stat($root.'/public_html/assets/home-halo.css');
$stamp=base_convert((string)$cssStat['mtime'],10,36).'-'.base_convert((string)$cssStat['size'],10,36);
$assert(str_ends_with($css,$release.'.'.$stamp),'Build URL reflects filesystem mtime and file size');
$assert(Build::asset('/assets/missing-file.css')==='/assets/missing-file.css?v='.$release,
    'Missing asset falls back safely to release stamp');
$assert(Build::asset('/manifest.webmanifest')==='/manifest.webmanifest?v='.$release,
    'Non-assets retain stable release token');
$assert(Build::asset('/assets/../../config.php')==='/assets/../../config.php?v='.$release,
    'Never stat untrusted traversal paths');

$view=file_get_contents($root.'/app/Core/View.php');
$assert(str_contains($view,"Build::asset('/assets/home-halo.css')"),'Halo stylesheet uses central fingerprinted URL');
$assert(str_contains($view,"Build::asset('/assets/home-halo.js')"),'Halo JS uses central fingerprinted URL');
$worker=file_get_contents($root.'/public_html/service-worker.js');
$assert(str_contains($worker,"const VERSION='epdi-v2.0.2.3'"),'Service worker cache generation rotated');
$assert(str_contains($worker,'REVISIONED_ASSET.test(revision)'),'Cache-first is restricted to immutable fingerprinted assets');
$assert(str_contains($worker,"cache:'no-cache'"),'Old nonfingerprinted CSS/JS revalidated');
$assert(str_contains($worker,'ignoreSearch:true'),'Offline fallback can load precached shell with different query');
$assert(str_contains($worker,"url.pathname.startsWith('/media/')"),'Protected media bypass retains no-store');
$assert(str_contains($worker,"url.pathname.startsWith('/api/v2/offline/')"),'Offline protected API bypass retained');
$assert(str_contains($worker,'skipWaiting()')&&str_contains($worker,'clients.claim()'),
    'New service worker takes over existing clients');
$assert(str_contains($worker,'epdi-v2'),'Previous SW cache families are expired');
$assert(str_contains(file_get_contents($root.'/public_html/.htaccess'),'immutable'),
    'High-performance immutable caching remains enabled for fingerprinted assets');

echo 'PASS asset fingerprint and PWA cache release contract'.PHP_EOL;
