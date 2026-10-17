<?php
declare(strict_types=1);
/**
 * Generate new *source-only* release SHA-256 manifest from tracked files.
 * Read-only: emit JSON to stdout and never include runtime ZIP backups.
 *
 * Usage: php bin/build-source-manifest.php [--summary]
 *
 * This does NOT replace the historical v2.0.2.1 SHA256SUMS, validate media
 * backups, or certify a production deployable artifact.
 */
if(PHP_SAPI!=='cli')exit(2);
$summary=in_array('--summary',$argv,true);
if(count(array_diff(array_slice($argv,1),['--summary']))>0){
    fwrite(STDERR,"Usage: php bin/build-source-manifest.php [--summary]\n");exit(2);
}
$repo=dirname(__DIR__,2);
$process=@proc_open(['git','-C',$repo,'ls-files','-z'],
  [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
if(!is_resource($process)){fwrite(STDERR,"Git index unavailable\n");exit(2);}
fclose($pipes[0]);$files=stream_get_contents($pipes[1]);fclose($pipes[1]);
$errors=stream_get_contents($pipes[2]);fclose($pipes[2]);
$code=proc_close($process);
if($code!==0||!is_string($files)){fwrite(STDERR,"Could not read Git index\n");exit(2);}
$paths=array_filter(explode("\0",$files),static fn($s)=>$s!=='');
sort($paths,SORT_STRING);
$entries=[];$bytes=0;
foreach($paths as$path){
  if(str_contains($path,'..')||str_starts_with($path,'/')||str_contains($path,'\\')){
      fwrite(STDERR,"Unexpected tracked path\n");exit(1);
  }
  if(str_starts_with($path,'codebase/storage/')
      &&!preg_match('#^codebase/storage/(?:cache|clips|logs|media|tmp|uploads)/\.(?:keep|gitkeep)$#D',$path)){
      fwrite(STDERR,"Unapproved runtime data in Git index\n");exit(1);
  }
  if(in_array($path,[
    'codebase/config/config.php','codebase/config/runtime.php',
    'codebase/config/install.token','codebase/storage/installed.lock'],true)){
      fwrite(STDERR,"Secret/runtime config in Git index\n");exit(1);
  }
  $file=$repo.'/'.$path;
  if(!is_file($file)||is_link($file)){fwrite(STDERR,"Tracked source missing or symlinked\n");exit(1);}
  $length=filesize($file);$hash=hash_file('sha256',$file);
  if(!is_int($length)||$hash===false){fwrite(STDERR,"Cannot read tracked file\n");exit(1);}
  $entries[]=['path'=>$path,'bytes'=>$length,'sha256'=>$hash];$bytes+=$length;
}
$overall=hash('sha256',implode("\n",array_map(
  static fn($r)=>$r['path']."\0".$r['bytes']."\0".$r['sha256'],$entries)));
$manifest=[
 'schema'=>'epdi-source-manifest-v1',
 'historical_release_sha256s_replaced'=>false,
 'deployable_artifact_verified'=>false,
 'tracked_files'=>count($entries),
 'tracked_bytes'=>$bytes,
 'index_digest_sha256'=>$overall
];
if(!$summary)$manifest['files']=$entries;
echo json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
