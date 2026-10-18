<?php
declare(strict_types=1);
require_once __DIR__.'/support/app-source.php';
$root=dirname(__DIR__);$fails=[];$pass=function(bool$c,string$m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};
$read=fn(string$f):string=>is_file($root.'/'.$f)?(string)file_get_contents($root.'/'.$f):'';
$catalog=$read('app/Services/SettingsCatalog.php');$app=epdiAppSource($root);$recovery=$read('app/Services/ProcessingRecovery.php');$pipeline=$read('app/Services/ProcessingPipeline.php');$legacy=$read('app/Services/Processing.php');$queue=$read('app/Services/QueueService.php');$css=$read('public_html/assets/admin-beta.css');$js=$read('public_html/assets/admin-beta.js');
foreach(['processing.highres_quality','processing.preview_quality','processing.thumbnail_quality','processing.tile_quality','processing.recovery_manual_smart','processing.recovery_auto_rescue','processing.recovery_max_rescues','processing.recovery_dpi_ceiling','processing.recovery_tile_size','processing.recovery_ocr_timeout_seconds'] as$key)$pass(str_contains($catalog,$key),'processing setting registered '.$key);
$pass(str_contains($app,'private function adminProcessingSettings')&&str_contains($app,'SAFE PRESETS')&&str_contains($app,'RECOVERY & REPROCESSING'),'dedicated Processing Control UI included');
$pass(str_contains($app,'PROCESSING FALLBACK')&&str_contains($app,'Open Infrastructure'),'existing fallback/infrastructure workflow retained');
$pass(str_contains($app,'ProcessingRecovery::manualSmartDefault()'),'manual Reprocess honors Publisher Control smart default');
foreach(['highres_quality','preview_quality','thumbnail_quality','tile_quality'] as$key){$pass(str_contains($pipeline,"processingInt('{$key}'"),'Rabbit pipeline consumes '.$key);$pass(str_contains($legacy,"processing.{$key}"),'legacy fallback consumes processing.'.$key);}
$pass(str_contains($recovery,'automaticRescueEnabled')&&str_contains($recovery,'automaticRescueLimit')&&str_contains($recovery,'canAutoRescue'),'bounded automatic rescue policy implemented');
$pass(str_contains($recovery,"processing.recovery_dpi_ceiling")&&str_contains($recovery,"processing.recovery_tile_size")&&str_contains($recovery,"processing.recovery_ocr_timeout_seconds"),'recommended recovery profile is publisher-configurable');
$pass(str_contains($queue,"'EDITION_RENDER'")&&str_contains($queue,"processing_profile'=>'auto_recovery'")&&str_contains($queue,'auto_rescue_attempt'),'render failure can enqueue one bounded recovery pipeline');
$pass(str_contains($pipeline,"'auto_recovery'=>true")&&str_contains($pipeline,'auto_rescue_parent_pipeline')&&str_contains($pipeline,'enqueueEditionRender'),'asset finalization can enqueue bounded recovery pipeline');
$pass(str_contains($js,'data-processing-preset')&&str_contains($js,"recovery:{"),'processing presets are interactive but unsaved until submit');
$pass(str_contains($css,'processing-settings-grid')&&str_contains($css,'processing-recovery-card')&&str_contains($css,'processing-fallback-card'),'responsive Processing Control design styles included');
$pass(!is_file($root.'/database/upgrade-v2.0.0-beta.1.2.sql'),'beta.1.2 requires no database migration');
if($fails){fwrite(STDERR,"\nPROCESSING CONTROL CHECK FAILED\n - ".implode("\n - ",$fails)."\n");exit(1);}echo"\nPASS Processing Control & Recovery Profiles\n";
