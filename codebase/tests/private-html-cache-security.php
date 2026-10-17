<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$http=(string)file_get_contents($root.'/app/Core/Http.php');
$sw=(string)file_get_contents($root.'/public_html/service-worker.js');
$checks=[
 'public html never cached for logged-in users'=>substr_count($http,'if(Auth::user()){self::privateNoStore();return;}')===2,
 'private response sets no-store'=>str_contains($http,'Cache-Control: private, no-store'),
 'public responses vary by cookie'=>str_contains($http,'Vary: Cookie, Accept-Encoding'),
 'dynamic navigation is network-only'=>str_contains($sw,'event.respondWith(fetch(request).catch('),
 'no dynamic cache.put'=>!str_contains($sw,'cache.put(request,cacheCopy)')&&!str_contains($sw,'await cache.put(request,cacheCopy)'),
 'clear previously stored dynamic HTML'=>str_contains($sw,'key===PAGE_CACHE'),
 'offline fallback remains'=>str_contains($sw,"caches.match('/offline.html')"),
 'encrypted offline API never cached'=>str_contains($sw,"url.pathname.startsWith('/api/v2/offline/')"),
];
foreach($checks as $what=>$ok)if(!$ok){fwrite(STDERR,"FAIL: $what\n");exit(1);}
echo "PASS private HTML and service-worker caching safeguards (".count($checks)." cases)\n";
