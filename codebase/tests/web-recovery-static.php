<?php
declare(strict_types=1);
require_once __DIR__.'/support/app-source.php';
$root=dirname(__DIR__);$fails=[];$ok=function(bool$c,string$m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};$read=fn(string$f)=>(string)@file_get_contents($root.'/'.$f);
$q=$read('app/Services/QueueService.php');$r=$read('app/Services/RecoveryCenter.php');$s=$read('app/Services/PublishingScheduler.php');$a=epdiAppSource($root);$p=$read('app/Services/ProductionReadiness.php');$bin=$read('bin/scheduler.php');
$ok(str_contains($q,'DISPATCH_CONFIRMED')&&str_contains($q,'MISSING_DISPATCH_RECEIPT'),'dispatch analysis distinguishes confirmed vs missing');
$ok(str_contains($q,'close_terminal')&&str_contains($q,'close_orphan')&&str_contains($q,'close_superseded')&&str_contains($q,'recover_confirm'),'failed outbox classifications are explicit');
$ok(str_contains($q,"else \$applied['unchanged']++"),'unknown failures remain unchanged during safe reconcile');
$ok(str_contains($q,'confirmed_dispatches_preserved'),'backward-compatible repair reports confirmed dispatch preservation');
$ok(str_contains($r,'RunOnce')===false&&str_contains($r,"PublishingScheduler::runOnce('web')"),'web scheduler invokes shared service, not shell execution');
$ok(!str_contains($r,'shell_exec')&&!str_contains($r,'supervisorctl'),'Recovery service does not shell out to scheduler/supervisor');
$ok(str_contains($a,'Apply safe reconcile')&&str_contains($a,'Repair missing dispatches')&&str_contains($a,'Capture / reset eviction baseline'),'web repair actions have explicit operator intent');
$ok(str_contains($a,'name="confirm" value="1" required')&&str_contains($a,"(\$_POST['confirm']??'')!=='1'"),'mutating reconciliation actions require confirmation');
$ok(str_contains($p,'oldest state activity')&&!str_contains($p,'oldest active'),'processing readiness no longer degrades from original creation age');
$ok(str_contains($s,'GET_LOCK("epdi_scheduler_v2",0)')&&str_contains($s,'RELEASE_LOCK("epdi_scheduler_v2")'),'scheduler retains overlap protection');
$ok(str_contains($bin,"PublishingScheduler::runOnce('cron')"),'CLI cron and web share one scheduler engine');
if($fails){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$fails)."\n");exit(1);}echo "\nPASS web recovery static guard\n";
