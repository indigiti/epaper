<?php
declare(strict_types=1);
/**
 * Git source-tree hygiene only; no customer data, production filesystem,
 * database, and no live backup validation is performed.
 */
$root=dirname(__DIR__,2);
$codebase=$root.'/codebase';
$allowed=[
 'codebase/storage/cache/.keep',
 'codebase/storage/clips/.keep',
 'codebase/storage/logs/.gitkeep',
 'codebase/storage/logs/.keep',
 'codebase/storage/media/.keep',
 'codebase/storage/tmp/.keep',
 'codebase/storage/uploads/.keep',
];
$command='git -C '.escapeshellarg($root).' ls-files --cached -z -- codebase/storage';
$handle=@popen($command,'r');
if(!is_resource($handle)){fwrite(STDERR,"FAIL: Cannot enumerate Git storage index\n");exit(2);}
$out=stream_get_contents($handle);
$status=pclose($handle);
if(!is_string($out)||$status!==0){fwrite(STDERR,"FAIL: Git storage enumeration failed\n");exit(2);}
$paths=array_values(array_filter(explode("\0",$out),static fn(string $p):bool=>$p!==''));
sort($paths);sort($allowed);
if($paths!==$allowed){
    $unexpected=array_values(array_diff($paths,$allowed));
    $missing=array_values(array_diff($allowed,$paths));
    fwrite(STDERR,"FAIL: Runtime data still tracked or placeholder missing. Extra ".count($unexpected)." / missing ".count($missing)."\n");
    exit(1);
}
foreach(['cache','clips','logs','media','tmp','uploads'] as$directory){
    if(!is_dir($codebase.'/storage/'.$directory)){
        fwrite(STDERR,"FAIL: Runtime directory placeholder missing: $directory\n");
        exit(1);
    }
}
foreach(['public_html/index.php','app/bootstrap.php','bin/scheduler.php','database/schema.sql','VERSION','composer.json','composer.lock','SHA256SUMS'] as$path){
    if(!is_file($codebase.'/'.$path)){fwrite(STDERR,"FAIL: Production entry-point or historical artifact missing: $path\n");exit(1);}
}
echo "PASS source tree: no runtime PDFs, clippings, media, logs or caches tracked (7 placeholders retained).\n";
echo "NOTICE: Git history still retains old data; historical SHA256SUMS is not a checksum for the slim source checkout.\n";
