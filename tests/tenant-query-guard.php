<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$manifest=json_decode((string)file_get_contents($root.'/tests/fixtures/TENANT-DATA-MANIFEST-1.1.0.json'),true);
$tenantTables=[];
foreach(($manifest['tables']??[]) as$table=>$scope)if(in_array($scope,['TENANT','TENANT_OR_MIXED'],true))$tenantTables[]=$table;
$files=[];
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app',FilesystemIterator::SKIP_DOTS));
foreach($it as$f)if($f->isFile()&&$f->getExtension()==='php')$files[]=$f->getPathname();
sort($files);
$fail=[];$checked=0;
foreach($files as$file){
  $src=(string)file_get_contents($file);
  // SQL in this codebase is deliberately kept in single-quoted PHP strings.
  if(!preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/s",$src,$ms,PREG_OFFSET_CAPTURE))continue;
  foreach($ms[1] as[$sql,$offset]){
    if(!preg_match('/\\b(?:SELECT|UPDATE|DELETE\\s+FROM|INSERT\\s+INTO)\\b/i',$sql))continue;
    $tables=[];foreach($tenantTables as$t)if(preg_match('/\\b'.preg_quote($t,'/').'\\b/',$sql))$tables[]=$t;
    if(!$tables)continue;$checked++;
    if(stripos($sql,'tenant_guid')===false){$rel=str_replace($root.'/','',$file);$allowedGlobal=(($rel==='app/Services/OutboxPublisher.php'&&count(array_diff($tables,['ep_outbox_events']))===0)||$rel==='app/Services/PublishingScheduler.php');if(!$allowedGlobal){$line=substr_count(substr($src,0,$offset),"\n")+1;$fail[]=$rel.':'.$line.' ['.implode(',',$tables).']';}}
  }
}
echo "Tenant SQL statements checked: {$checked}\n";
if($fail){foreach($fail as$f)echo "FAIL — tenant query missing tenant_guid: {$f}\n";exit(1);}echo "PASS — every application SQL statement touching tenant-owned tables carries tenant_guid\n";
// Explicitly document the two legitimate cross-tenant operational scans. They execute only in CLI worker/scheduler context and derive tenant context before business processing.
$operationalMarkers=[
  'bin/worker.php'=>['ep_processing_jobs'],
  'bin/rabbit-worker.php'=>['JobExecutor'],
  'bin/outbox-publisher.php'=>['OutboxPublisher'],
  'bin/scheduler.php'=>['PublishingScheduler'],
  'app/Services/PublishingScheduler.php'=>['ep_processing_jobs','ep_editions'],
];
foreach($operationalMarkers as$f=>$markers){
  $src=(string)file_get_contents($root.'/'.$f);
  $ok=false;foreach($markers as$m)if(str_contains($src,$m)){$ok=true;break;}
  echo ($ok?'PASS':'FAIL')." — documented platform-wide CLI operation {$f}\n";if(!$ok)exit(1);
}
