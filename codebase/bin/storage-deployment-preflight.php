<?php
declare(strict_types=1);
/**
 * Non-mutating staging storage preflight:
 * php bin/storage-deployment-preflight.php --app-root=/staging/release \
 *   --persistent-storage=/staging/persistent/storage \
 *   --backup-inventory=/private/backup-storage-inventory.json
 */
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/app/Core/RestoreInventory.php';
require_once dirname(__DIR__).'/app/Core/StorageDeploymentSafety.php';
use EpaperSaas\Core\StorageDeploymentSafety;
$options=[];
foreach(array_slice($argv,1) as$arg){
  if(preg_match('/^--(app-root|persistent-storage|backup-inventory)=(.+)$/D',$arg,$m))$options[$m[1]]=$m[2];
  else{fwrite(STDERR,"Invalid arguments; inspect the script usage header.\n");exit(2);}
}
foreach(['app-root','persistent-storage','backup-inventory'] as$required){
  if(!isset($options[$required])||!str_starts_with($options[$required],'/')){
    fwrite(STDERR,"Three absolute paths required: --app-root, --persistent-storage, --backup-inventory.\n");exit(2);
  }
}
try{
  $file=realpath($options['backup-inventory']);
  if(!$file||!is_file($file)||!is_readable($file))throw new RuntimeException('Missing inventory');
  $baseline=json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
  if(!is_array($baseline))throw new RuntimeException('Invalid inventory');
  $check=StorageDeploymentSafety::inspect($options['app-root'],$options['persistent-storage'],$baseline);
  echo json_encode($check,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
  exit($check['ok']?0:1);
}catch(Throwable){
  fwrite(STDERR,"Storage preflight could not complete. Inspect private staging configuration.\n");exit(2);
}
