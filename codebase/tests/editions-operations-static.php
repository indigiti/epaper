<?php
declare(strict_types=1);
require_once __DIR__.'/support/app-source.php';
$root=dirname(__DIR__);$fails=[];$pass=function(bool$ok,string$name)use(&$fails){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fails[]=$name;};
require_once $root.'/app/Services/SchemaDoctor.php';
$ref=new ReflectionClass(EpaperSaas\Services\SchemaDoctor::class);$m=$ref->getMethod('splitSql');$m->setAccessible(true);
$sql=(string)file_get_contents($root.'/database/upgrade-v2.0.0-beta.1.1.sql');$parts=$m->invoke(null,$sql);$upper=strtoupper($sql);
$pass(count($parts)===3,'beta.1.1 migration splits into two ALTER statements and one backfill');
$pass(str_contains($sql,'ADD COLUMN publish_mode')&&str_contains($sql,'ADD COLUMN schedule_timezone'),'publishing intent columns included');
$pass(str_contains($sql,'ix_edition_publish_schedule')&&str_contains($sql,'ix_processing_edition_type_created'),'Editions and processing indexes included');
$pass(!str_contains($upper,'DROP TABLE')&&!str_contains($upper,'TRUNCATE ')&&!str_contains($upper,'DELETE FROM '),'beta.1.1 migration contains no destructive DDL/DML');
$app=epdiAppSource($root);$pipeline=(string)file_get_contents($root.'/app/Services/ProcessingPipeline.php');$scheduler=(string)file_get_contents($root.'/app/Services/PublishingScheduler.php');
$pass(str_contains($app,'EditionOperations::active($guid)') && str_contains($app,"['PUBLISHED','ARCHIVED','SCHEDULED','TRASHED']"),'reprocess blocks public/archived/active/scheduled editions');
$pass(str_contains($app,'Previous attempts remain available')&&str_contains($app,'View processing history'),'reprocess UI preserves attempt history semantics');
$pass(str_contains($pipeline,"foreach(['processing_overrides','processing_profile','reprocess_reason']"),'processing recovery profile propagates to page jobs');
$pass(str_contains($scheduler,'status IN ("READY","SCHEDULED")')&&str_contains($scheduler,'publish_mode="AUTO_READY"'),'scheduler publishes only successfully processed editions');
if($fails){fwrite(STDERR,"\nEDITIONS OPERATIONS STATIC TEST FAILED\n");exit(1);}echo"\nEDITIONS OPERATIONS STATIC TEST PASSED\n";
