<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$http=(string)file_get_contents($root.'/app/Core/Http.php');
$sw=(string)file_get_contents($root.'/public_html/service-worker.js');
$navStart=strpos($sw,"if(request.mode==='navigate'){");
$navEnd=$navStart!==false?strpos($sw,"if(url.pathname.startsWith('/assets/')",$navStart):false;
$navBlock=$navStart!==false&&$navEnd!==false?substr($sw,$navStart,$navEnd-$navStart):'';
$navNoCache=$navBlock!==''&&!str_contains($navBlock,'cache.put(')&&!str_contains($navBlock,'cache.add(');
$checks=[
 'public html never cached for logged-in users'=>substr_count($http,'if(Auth::user()){self::privateNoStore();return;}')===2,
 'private response sets no-store'=>str_contains($http,'Cache-Control: private, no-store'),
 'public responses vary by cookie'=>str_contains($http,'Vary: Cookie, Accept-Encoding'),
 'dynamic navigation is network-only'=>str_contains($sw,'event.respondWith(fetch(request).catch('),
 'no dynamic cache.put'=>$navNoCache,
 'clear previously stored dynamic HTML'=>str_contains($sw,'key===PAGE_CACHE'),
 'offline fallback remains'=>str_contains($sw,"caches.match('/offline.html')"),
 'encrypted offline API never cached'=>str_contains($sw,"url.pathname.startsWith('/api/v2/offline/')"),
];
foreach($checks as $what=>$ok)if(!$ok){fwrite(STDERR,"FAIL: $what\n");exit(1);}
echo "PASS private HTML and service-worker caching safeguards (".count($checks)." cases)\n";
