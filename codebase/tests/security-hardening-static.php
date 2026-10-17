<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$ok=function(bool$c,string$m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};
$app=(string)file_get_contents($root.'/app/App.php');$err=(string)file_get_contents($root.'/app/Services/ErrorCenter.php');$store=(string)file_get_contents($root.'/app/Core/Store.php');$sched=(string)file_get_contents($root.'/app/Services/PublishingScheduler.php');
foreach(["analytics:","offline-device:","offline-license:","offline-page:","offline-complete:","progress:","search:"] as$key)$ok(str_contains($app,"RateLimiter::allow('{$key}"),'rate limiter '.$key);
$ok(substr_count($app,"'code'=>'RATE_LIMITED'")>=7,'rate-limited paths return explicit 429 code');
$ok(str_contains($err,'try{PlatformClient::instance()->error(')&&str_contains($err,'catch(\\Throwable){}'),'Error Center reporting cannot cascade on Platform outage');
$ok(!str_contains($store,'params')||!str_contains($store,"slow_samples'=>\$params"),'slow-query samples do not persist SQL parameters');
$ok(str_contains($store,"substr(hash('sha256',\$normalized),0,16)")&&str_contains($store,"'verb'=>\$verb"),'slow-query samples store only verb/signature/timing');
$ok(str_contains($sched,'GET_LOCK("epdi_scheduler_v2",0)')&&str_contains($sched,'RELEASE_LOCK("epdi_scheduler_v2")'),'scheduler overlap lock is released safely');
if($fails){fwrite(STDERR,"\nSECURITY HARDENING STATIC FAILED\n - ".implode("\n - ",$fails)."\n");exit(1);}echo"\nPASS security hardening static guard\n";
