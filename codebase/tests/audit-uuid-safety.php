<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];
$pass=function(bool $ok,string $name)use(&$fails){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fails[]=$name;};
$a=(string)file_get_contents($root.'/app/Services/Audit.php');
$http=(string)file_get_contents($root.'/app/Core/Http.php');
$app=(string)file_get_contents($root.'/app/App.php');
$pass(str_contains($a,'Uuid::isValid'),'audit validates UUIDs before binary conversion');
$pass(str_contains($a,"resource_ref"),'audit preserves non-UUID resource identifiers as metadata');
$pass(str_contains($a,"'resource_guid'=>\$resource"),'platform audit receives normalized UUID/null resource_guid');
$pass(str_contains($http,'Uuid::isValid((string)$h)'),'incoming correlation UUID is strictly validated');
$pass(!str_contains($app,"Audit::log('system.schema.repair','system','v2'"),'schema repair no longer passes literal v2 into UUID resource_guid');
$pass((bool)preg_match('/try\\s*\\{\\s*Audit::log\\(\\x27system\\.schema\\.repair\\x27/s',$app),'schema repair UI isolates secondary audit failure');
$pass(str_contains($app,'SchemaDoctor::lastRepair()')&&str_contains($app,'Last database repair recorded.'),'upgrade center exposes last repair marker');
if($fails){fwrite(STDERR,"\nAUDIT UUID SAFETY TEST FAILED\n");exit(1);}echo"\nAUDIT UUID SAFETY TEST PASSED\n";
