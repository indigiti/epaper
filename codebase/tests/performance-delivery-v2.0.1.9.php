<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$ok=function(bool$c,string$m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};$read=fn(string$f)=>(string)@file_get_contents($root.'/'.$f);
$version=trim($read('VERSION'));$bootstrap=$read('app/bootstrap.php');$app=$read('app/App.php');$auth=$read('app/Core/Auth.php');$http=$read('app/Core/Http.php');$view=$read('app/Core/View.php');$resolver=$read('app/Services/DomainResolver.php');$media=$read('app/Services/Media.php');$tenant=$read('app/Services/TenantData.php');$perf=$read('app/Services/PerformanceTelemetry.php');$js=$read('public_html/assets/app.js');$sw=$read('public_html/service-worker.js');
$ok(version_compare($version,'2.0.1.9','>='),'VERSION remains at or above v2.0.1.9');
$ok(!str_contains($bootstrap,'Auth::start();')&&str_contains($bootstrap,'PerformanceTelemetry::begin()'),'bootstrap no longer opens every request session');
$ok(str_contains($auth,'startIfPresent')&&str_contains($auth,'hasSessionCookie')&&str_contains($auth,'session_write_close'),'lazy session + explicit lock release packaged');
$ok(str_contains($view,'$bootCsrf=($workspace||$auth)?Http::csrf()')&&str_contains($view,"_SESSION['_csrf']")&&str_contains($js,'/api/v1/session/csrf'),'public CSRF/session bootstrap is lazy');
$ok(str_contains($http,'publicStaticCache')&&str_contains($http,'Vary: Cookie, Accept-Encoding'),'personalized public variants retain Cookie safety while static feeds get cookie-free cache');
$mediaPos=strpos($app,"preg_match('#^/media/");$revalidatePos=strpos($app,'if(Auth::user())Auth::sessionAccessActive()');$ok($mediaPos!==false&&$revalidatePos!==false&&$mediaPos<$revalidatePos,'signed media dispatch precedes session/account validation');
$ok(str_contains($resolver,'RedisStore::getJson($cacheKey)')&&str_contains($resolver,'fileCacheGet($cacheKey)')&&str_contains($resolver,'domain:binding-v4:')&&str_contains($resolver,"tenant-synced:")&&str_contains($resolver,'if($changed)Store::exec'),'control-plane cache + change-only tenant reconciliation packaged');
$ok(str_contains($media,"PerformanceTelemetry::mediaDelivery('x-accel')")&&str_contains($media,'X-EPDI-Media-Delivery: x-accel'),'media offload mode is observable');
$ok(str_contains($app,'LEFT JOIN (SELECT tenant_guid,user_guid,MAX(device_label) trusted_device')&&!str_contains($app,'(SELECT rd.device_label FROM ep_reader_devices'),'Subscribers uses aggregated trusted-device/session joins');
$ok(str_contains($tenant,'SUM(CASE WHEN status IN ("QUEUED","RUNNING","RETRY_WAIT")')&&str_contains($tenant,'latest_processing_error'),'edition processing summaries use grouped aggregate');
$ok(str_contains($perf,'p95_ms')&&str_contains($perf,'perf:vitals:')&&str_contains($app,'REAL REQUEST TELEMETRY'),'rolling request/Web Vitals telemetry packaged');
$ok(str_contains($js,"Math.random()<Number(cfg.rumSampleRate||0.10)")&&str_contains($js,"'/api/v1/performance/vitals'"),'privacy-minimal browser Web Vitals sampling packaged');
$ok(str_contains($sw,"const VERSION='epdi-v{$version}'")&&!str_contains($sw,'?v=2.0.1.4'),'Service Worker and shell asset versions aligned');
$ok(!is_file($root.'/database/upgrade-'.$version.'.sql'),'current release requires no schema migration');
if($fails){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$fails)."\n");exit(1);}echo"\nPASS cumulative performance/delivery guard\n";
