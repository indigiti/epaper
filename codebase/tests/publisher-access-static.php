<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Services/PublisherAccess.php';
use EpaperSaas\Services\PublisherAccess;
$id='12345678-1234-1234-1234-123456789abc';
$cases=[
 ['OWNER','/admin/system/upgrade','POST',true],
 ['ADMIN','/admin/system/infrastructure/rabbitmq/save','POST',true],
 ['ADMIN','/admin/processing/repair-queue','POST',true],
 ['PUBLISHER_ADMIN','/admin/system/infrastructure','GET',false],
 ['PUBLISHER_ADMIN','/admin/system/recovery.json','GET',false],
 ['PUBLISHER_ADMIN','/admin/system/upgrade','POST',false],
 ['PUBLISHER_ADMIN','/admin/processing/repair-queue','POST',false],
 ['PUBLISHER_ADMIN','/admin/settings/reader','POST',true],
 ['EDITOR','/admin/editions','POST',true],
 ['EDITOR','/admin/articles','POST',true],
 ['EDITOR','/admin/highlights','POST',true],
 ['EDITOR','/admin/settings/seo','POST',false],
 ['EDITOR','/admin/subscribers/'.$id.'/status','POST',false],
 ['EDITOR','/admin/publications','POST',false],
 ['EDITOR','/admin/publications','GET',true],
 ['EDITOR','/admin/system/recovery','GET',false],
 ['EDITOR','/admin/system/recovery.json','GET',false],
 ['EDITOR','/admin/processing','GET',true],
 ['EDITOR','/admin/processing/'.$id,'GET',true],
 ['EDITOR','/admin/processing/'.$id,'POST',false],
 ['EDITOR','/admin/navigation/scope/create','POST',true],
 ['EDITOR','/admin/editions/'.$id.'/delete','POST',true],
 ['OPERATOR','/admin/processing/'.$id,'GET',true],
 ['OPERATOR','/admin/processing/'.$id,'POST',false],
 ['OPERATOR','/admin/editions','GET',true],
 ['OPERATOR','/admin/editions','POST',false],
 ['OPERATOR','/admin/editions/'.$id.'/process-now','POST',true],
 ['OPERATOR','/admin/editions/'.$id.'/reprocess','POST',true],
 ['OPERATOR','/admin/editions/'.$id.'/publish','POST',false],
 ['OPERATOR','/admin/editions/'.$id.'/delete','POST',false],
 ['OPERATOR','/admin/publications','POST',false],
 ['OPERATOR','/admin/processing/repair-queue','POST',false],
 ['OPERATOR','/admin/processing/repair-queue/anything','POST',false],
 ['OPERATOR','/admin/performance/cache-action','POST',false],
 ['OPERATOR','/admin/performance','GET',true],
 ['OPERATOR','/admin/performance','POST',false],
 ['OPERATOR','/admin/system/readiness.json','GET',true],
 ['OPERATOR','/admin/system/readiness.json/mutate','POST',false],
 ['OPERATOR','/admin/preferences/theme','POST',true],
 ['VIEWER','/admin','GET',false],
 ['EDITOR','/admin','DELETE',false],
 ['EDITOR','/admin','POST',false],
 ['EDITOR','/account','GET',false],
];
$fails=0;
foreach($cases as [$role,$path,$method,$expected]){
    $actual=PublisherAccess::allows($role,$path,$method);
    if($actual!==$expected){fwrite(STDERR,"FAIL publisher authorization: $role $method $path\n");$fails++;}
}
$app=(string)file_get_contents(dirname(__DIR__).'/app/App.php');
if(!str_contains($app,'PublisherAccess::allows(')){fwrite(STDERR,"FAIL dispatcher is not guarded\n");$fails++;}
if($fails)exit(1);
echo "PASS publisher route authorization (".count($cases)." cases)\n";
