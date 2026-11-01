<?php
/**
 * Read-only file inventory for Cloudways ePaper cutover.
 * Does not open databases or print private file names or contents.
 *
 * Before storage move:
 *   php bin/storage-inventory.php --output=/path/outside_webroot/before.json
 * After restore/mount:
 *   php bin/storage-inventory.php --compare=/path/outside_webroot/before.json
 *
 * The fingerprint verifies relative names, sizes and file digests for
 * uploaded newspaper media, PDFs, clips and branding, excluding live caches/logs.
 */
declare(strict_types=1);

$root=dirname(__DIR__);
$groups=[
    'newspaper_media'=>$root.'/storage/media',
    'original_pdfs'=>$root.'/storage/uploads',
    'clippings'=>$root.'/storage/clips',
    'public_branding'=>$root.'/public_html/uploads',
];
$args=array_slice($argv,1);
$arg=static function(string $name)use($args):?string{
    foreach($args as $v)if(str_starts_with($v,'--'.$name.'='))return substr($v,strlen($name)+3);
    return null;
};
$result=['schema'=>'epdi.storage.inventory.v1','groups'=>[]];
$allHash=hash_init('sha256');
foreach($groups as $name=>$dir){
    $base=realpath($dir);
    $count=0;$bytes=0;$groupHash=hash_init('sha256');
    if($base){
        $files=[];
        $it=new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach($it as $file){
            if(!$file->isFile()||$file->isLink())continue;
            $relative=ltrim(substr($file->getPathname(),strlen($base)),DIRECTORY_SEPARATOR);
            if(in_array(basename($relative),['.keep','.gitkeep','.htaccess'],true))continue;
            $files[$relative]=$file->getPathname();
        }
        ksort($files,SORT_STRING);
        foreach($files as $relative=>$path){
            $size=filesize($path);
            $digest=hash_file('sha256',$path);
            if($size===false||$digest===false)throw new RuntimeException('Unable to hash private storage');
            hash_update($groupHash,$relative."\0".$size."\0".$digest."\n");
            $count++;$bytes+=$size;
        }
    }
    $digest=hash_final($groupHash);
    hash_update($allHash,$name."\0".$count."\0".$bytes."\0".$digest."\n");
    $result['groups'][$name]=['directory_exists'=>$base!==false,'count'=>$count,'bytes'=>$bytes,'sha256'=>$digest];
}
$result['sha256']=hash_final($allHash);
$result['data_present']=(($result['groups']['newspaper_media']['count']??0)>0)&&(($result['groups']['original_pdfs']['count']??0)>0);
$comparison=$arg('compare');
if($comparison!==null){
    if(!is_file($comparison))throw new RuntimeException('Private comparison manifest not found');
    $expected=json_decode((string)file_get_contents($comparison),true);
    if(!is_array($expected)||($expected['schema']??'')!==$result['schema'])throw new RuntimeException('Invalid private comparison manifest');
    $result['matches_baseline']=hash_equals((string)($expected['sha256']??''),$result['sha256']);
}
$result['verified']=$result['data_present']&&(!isset($result['matches_baseline'])||$result['matches_baseline']);
$payload=json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
if($target=$arg('output')){
    if(str_starts_with(realpath(dirname($target))?:'',$root.'/public_html'))throw new RuntimeException('Do not export inventory into webroot');
    if(file_put_contents($target,$payload)===false)throw new RuntimeException('Unable to save private inventory');
}else echo $payload;
exit($result['verified']?0:1);
