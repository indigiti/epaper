<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/RestoreInventory.php';
require_once dirname(__DIR__).'/app/Core/StorageDeploymentSafety.php';
use EpaperSaas\Core\{RestoreInventory,StorageDeploymentSafety};
$root=sys_get_temp_dir().'/epdi-deploy-preflight-'.bin2hex(random_bytes(7));
$app=$root.'/application';$persist=$root.'/persistent';$other=$root.'/other';
mkdir($app.'/app',0700,true);mkdir($app.'/public_html',0700,true);
file_put_contents($app.'/app/bootstrap.php','<?php');
file_put_contents($app.'/public_html/index.php','<?php');
foreach([$persist,$other] as$storage){
 foreach(['media','uploads','clips','cache','logs'] as$p)mkdir($storage.'/'.$p,0700,true);
 file_put_contents($storage.'/media/page.webp','fixture media');
 file_put_contents($storage.'/uploads/source.pdf','fixture original PDF');
 file_put_contents($storage.'/clips/clip.webp','fixture clipping');
}
$cases=0;$fails=0;
$assert=static function(bool $ok,string $why)use(&$cases,&$fails):void{$cases++;if(!$ok){$fails++;fwrite(STDERR,"FAIL storage preflight: $why\n");}};
try{
 $baseline=RestoreInventory::snapshot($persist);
 symlink($persist,$app.'/storage');
 $good=StorageDeploymentSafety::inspect($app,$persist,$baseline);
 $assert($good['ok']===true,'matching external symlink accepted');
 $assert($good['storage_link_matches_persistent']===true,'target matches');
 $assert($good['production_deployment_approved']===false,'not production approval');
 $assert($good['credentials_rotated_verified']===false,'no secret rotation claim');
 $assert($good['deployment_driver_verified']===false,'no deployer claim');
 $wrong=StorageDeploymentSafety::inspect($app,$other,$baseline);
 $assert($wrong['ok']===false,'wrong persistent target denied');
 file_put_contents($persist.'/media/page.webp','tampered');
 $bad=StorageDeploymentSafety::inspect($app,$persist,$baseline);
 $assert($bad['ok']===false,'modified media denied');
 file_put_contents($persist.'/media/page.webp','fixture media');
 unlink($app.'/storage');
 mkdir($app.'/storage');
 foreach(['media','uploads','clips','cache','logs'] as$p)mkdir($app.'/storage/'.$p);
 $physical=StorageDeploymentSafety::inspect($app,$persist,$baseline);
 $assert($physical['ok']===false,'in-checkout non-symlink denied');
 $inside=StorageDeploymentSafety::inspect($app,$app.'/storage',$baseline);
 $assert($inside['ok']===false,'internal data cannot pass external check');
}finally{
 foreach([$persist,$other] as$dir){
  foreach(['media','uploads','clips'] as$p)foreach(glob($dir.'/'.$p.'/*')?:[] as$f)@unlink($f);
  foreach(['media','uploads','clips','cache','logs'] as$p)@rmdir($dir.'/'.$p);
  @rmdir($dir);
 }
 if(is_link($app.'/storage'))@unlink($app.'/storage');
 foreach(['media','uploads','clips','cache','logs'] as$p)@rmdir($app.'/storage/'.$p);
 @rmdir($app.'/storage');
 @unlink($app.'/app/bootstrap.php');@unlink($app.'/public_html/index.php');
 @rmdir($app.'/app');@rmdir($app.'/public_html');@rmdir($app);@rmdir($root);
}
if($fails)exit(1);
echo "PASS persistent ePaper storage deployment preflight ($cases assertions)\n";
