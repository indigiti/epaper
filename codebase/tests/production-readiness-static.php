<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$ok=function(bool$c,string$m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};
$r=(string)file_get_contents($root.'/app/Services/ProductionReadiness.php');$app=(string)file_get_contents($root.'/app/App.php');$pub=(string)file_get_contents($root.'/app/Services/PublishingRuntime.php');$cli=(string)file_get_contents($root.'/bin/readiness-check.php');
foreach(['MariaDB','Redis','Digiti Platform','Storage','pdftoppm','tesseract','Imagick','Processing backlog','RabbitMQ','Transactional outbox','Supervisor workers','Publishing scheduler','Edition manifests','Multi-layer cache','Encrypted Offline Vault','Semantic SEO & discovery','Security baseline','Distributed rate limiting','Release artifact integrity','Backup / restore','Browser / device QA'] as$marker)$ok(str_contains($r,$marker),'readiness probe '.$marker);
$ok(str_contains($r,"'required'=>\$required"),'readiness records required-vs-manual checks');
$ok(str_contains($r,"PHP_SAPI==='cli'")&&str_contains($r,"'https_probe'=>\$cli?'DEFERRED_TO_WEB':'CHECKED'"),'CLI readiness does not falsely fail HTTPS-only probe');
$ok(str_contains($r,'$limit=5000')&&str_contains($r,"'truncated'=>\$truncated"),'readiness temp scan is bounded');
$ok(str_contains($app,'private function adminReadinessJson(): never'),'admin readiness JSON handler exists');
$ok(str_contains($pub,'scheduler_age_seconds')&&str_contains($pub,"'code'=>'SCHEDULER'"),'dashboard attention includes scheduler heartbeat');
$ok(str_contains($cli,'ProductionReadiness::snapshot(false)')&&str_contains($cli,'--tenant='),'CLI production readiness command packaged');
if($fails){fwrite(STDERR,"\nPRODUCTION READINESS STATIC FAILED\n - ".implode("\n - ",$fails)."\n");exit(1);}echo"\nPASS production readiness static guard\n";
