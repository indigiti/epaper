<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/TrackedFilePolicy.php';
use EpaperSaas\Core\TrackedFilePolicy;

$root=dirname(__DIR__,2);
$command='git -C '.escapeshellarg($root).' ls-files -z';
$pipe=@popen($command,'r');
if(!is_resource($pipe)){
    fwrite(STDERR,"ERROR: Cannot enumerate tracked files; refusing to certify source hygiene.\n");
    exit(2);
}
$data=stream_get_contents($pipe);
$status=pclose($pipe);
if(!is_string($data)||$status!==0){
    fwrite(STDERR,"ERROR: git ls-files failed; refusing to certify source hygiene.\n");
    exit(2);
}
$tracked=explode("\0",$data);
$violations=[];$legacyRuntime=0;
foreach($tracked as $file){
    if($file==='')continue;
    if(TrackedFilePolicy::prohibited($file))$violations[]=$file;
    if(str_starts_with($file,'codebase/storage/'))$legacyRuntime++;
}
if($violations){
    fwrite(STDERR,"BLOCKED: prohibited tracked runtime/credential paths:\n".implode("\n",$violations)."\n");
    exit(1);
}
echo "PASS: no prohibited runtime/credential paths in currently tracked files.\n";
if($legacyRuntime)echo "NOTICE: $legacyRuntime previously tracked codebase/storage files still require a separately backed-up migration.\n";
