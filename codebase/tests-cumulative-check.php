<?php
declare(strict_types=1);
require_once __DIR__.'/tests/support/app-source.php';
$root=__DIR__;$fails=[];$ok=function(bool $c,string $m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};
$version=trim((string)file_get_contents($root.'/VERSION'));
$ok((bool)preg_match('/^2\.0\.[0-9]+(?:\.[0-9]+)*(?:-(?:alpha\.[0-9]+(?:\.[0-9]+)*|beta\.[0-9]+(?:\.[0-9]+)*|rc\.[0-9]+(?:\.[0-9]+)*))?$/',$version),'current v2 VERSION format');
foreach(['app/Services/RabbitMq.php','app/Services/QueueService.php','app/Services/ProcessingPipeline.php','app/Services/InfrastructureConfig.php','app/Services/SupervisorRuntime.php','bin/rabbit-worker.php','bin/outbox-publisher.php','bin/supervisor-preflight.php','composer.json'] as$f)$ok(is_file($root.'/'.$f),'cumulative infrastructure '.$f);
$app=epdiAppSource($root);
$ok(str_contains($app,"/admin/system/infrastructure"),'infrastructure route');
$ok(str_contains($app,"/admin/system/infrastructure/supervisor"),'Supervisor setup route');
$ok(str_contains($app,'QUEUE_ENGINE_INCOMPLETE'),'queue incomplete guard');
$ok(str_contains($app,'current cumulative ePaper SaaS release package'),'current-release recovery wording');
$sw=(string)file_get_contents($root.'/public_html/service-worker.js');
$ok(str_contains($sw,"epdi-v{$version}"),'service worker aligned with VERSION');
// Historical parity artefacts remain protected across the v2 architecture work.
foreach(['release/CITY-EPAPER-COMPATIBILITY-1.1.4.json','release/READER-BEHAVIORS-1.1.4.json','release/PROCESSING-STAGES-1.1.4.json','release/FINAL-REAUDIT-1.1.4.md'] as$f)$ok(is_file($root.'/'.$f),'legacy parity release artifact '.$f);
$pipeline=(string)file_get_contents($root.'/app/Services/ProcessingPipeline.php');
$fallback=(string)file_get_contents($root.'/app/Services/Processing.php');
$ok((str_contains($pipeline,"'HIGHRES', 'image/webp'")||str_contains($pipeline,"'HIGHRES','image/webp'")),'Rabbit HIGHRES WebP');
$ok((str_contains($fallback,"'HIGHRES', 'image/webp'")||str_contains($fallback,"'HIGHRES','image/webp'")),'fallback HIGHRES WebP');
$ok(is_file($root.'/storage/logs/.gitkeep'),'Supervisor log directory packaged');
if($fails){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$fails)."\n");exit(1);}echo "\nPASS cumulative v2 guard\n";
