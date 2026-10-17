<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/BackupZipPreflight.php';
use EpaperSaas\Core\BackupZipPreflight;
$archive='';$readAll=false;
foreach(array_slice($argv,1) as$arg){
    if(str_starts_with($arg,'--zip='))$archive=substr($arg,6);
    elseif($arg==='--verify-contents')$readAll=true;
    else{fwrite(STDERR,"Usage: php bin/backup-zip-preflight.php --zip=/private/backup.zip [--verify-contents]\n");exit(2);}
}
if($archive===''||!str_starts_with($archive,'/')){
    fwrite(STDERR,"Use an absolute path to a private local ZIP backup.\n");exit(2);
}
try{
    $out=BackupZipPreflight::inspect($archive,$readAll);
    echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit($out['archive_structure_ok']?0:1);
}catch(Throwable $e){
    // Do not echo the archive path, ZIP member names or encrypted credentials.
    fwrite(STDERR,"ZIP preflight failed: invalid/unreadable archive or unsupported archive format.\n");
    exit(2);
}
