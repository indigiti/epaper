<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/Services/ClipCropCache.php';
$cache=\EpaperSaas\Services\ClipCropCache::class;
$uuid=static function():string {
 $h=bin2hex(random_bytes(16));
 return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-a'.substr($h,17,3).'-'.substr($h,20,12);
};
$tenant=$uuid();$otherTenant=$uuid();$page=$uuid();$otherPage=$uuid();
$box=['x'=>.1,'y'=>.2,'w'=>.45,'h'=>.35];
$src=tempnam(sys_get_temp_dir(),'epdi-source-');
$dest=[];
$calls=0;
$render=static function(string $out)use(&$calls):void {
 $calls++;
 if(file_put_contents($out,'mock-webp-'.$calls)===false)
  throw new RuntimeException('Cannot generate synthetic crop');
};
try{
 file_put_contents($src,'original-highres-page-v1');
 $sourceHash=hash_file('sha256',$src);
 $key1=$cache::key($tenant,$page,$sourceHash,$box);
 if($key1!==$cache::key($tenant,$page,$sourceHash,$box) ||
    $key1===$cache::key($otherTenant,$page,$sourceHash,$box) ||
    $key1===$cache::key($tenant,$otherPage,$sourceHash,$box) ||
    $key1===$cache::key($tenant,$page,$sourceHash,['x'=>.1000002,'y'=>.2,'w'=>.45,'h'=>.35]))
  throw new RuntimeException('Cache keys must scope exact content, tenant, page and geometry');
 $make=static function(string $tenant,string $page,array $box)use($cache,$src,$render,&$dest):array{
   $out=tempnam(sys_get_temp_dir(),'epdi-crop-');
   unlink($out);
   $out.='.webp';$dest[]=$out;
   return $cache::materialize($src,$out,$tenant,$page,$box,$render);
 };
 $first=$make($tenant,$page,$box);
 if($first['hit']!==false||$calls!==1)throw new RuntimeException('First clip must generate one crop');
 $second=$make($tenant,$page,$box);
 if($second['hit']!==true||$calls!==1)throw new RuntimeException('Second reader must reuse exactly one crop');
 // On Cloudways PHP-FPM link() is absent from the runtime. Verify that the
 // cache still serves readers through copy(), rather than throwing a fatal.
 if(!function_exists('link')&&function_exists('copy')){
    if($first['mode']!=='copy'||$second['mode']!=='copy')
        throw new RuntimeException('Disabled link() must use copy mode');
 }
 if(file_get_contents($dest[0])!==file_get_contents($dest[1]))
  throw new RuntimeException('Shared cached crop differs from user media');
 if($dest[0]===$dest[1])throw new RuntimeException('Readers may not share storage record paths');
 // Per-clip storage is independent of cache namespace.
 $make($tenant,$otherPage,$box);
 $make($otherTenant,$page,$box);
 $make($tenant,$page,['x'=>.100001,'y'=>.2,'w'=>.45,'h'=>.35]);
 if($calls!==4)throw new RuntimeException('Unrelated images/pages/tenants must not share crops');
 file_put_contents($src,'original-highres-page-v2');
 $make($tenant,$page,$box);
 if($calls!==5)throw new RuntimeException('Replacing high-resolution pixels must invalidate crop cache');
 $cache::prune($tenant,40);
 echo "Content-addressed crop cache: dedupe, tenant isolation and invalidation OK\n";
}finally{
 foreach($dest as $f)if(is_file($f))@unlink($f);
 if(is_file($src))@unlink($src);
 // Only remove this test run's random-tenant cache, never application data.
 foreach([$tenant,$otherTenant] as $id){
  $base=$root.'/storage/clip-crop-cache/'.$id;
  if(!is_dir($base))continue;
  foreach(new FilesystemIterator($base,FilesystemIterator::SKIP_DOTS) as $dir){
    if(!$dir->isDir())continue;
    foreach(new FilesystemIterator($dir->getPathname(),FilesystemIterator::SKIP_DOTS) as $file)
      if($file->isFile())@unlink($file->getPathname());
    @rmdir($dir->getPathname());
  }
  @rmdir($base);
 }
}
