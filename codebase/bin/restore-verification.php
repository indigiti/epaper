<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/RestoreInventory.php';
use EpaperSaas\Core\RestoreInventory;

$options=[];
foreach(array_slice($argv,1) as$arg){
    if(str_starts_with($arg,'--storage='))$options['storage']=substr($arg,10);
    elseif(str_starts_with($arg,'--compare='))$options['compare']=substr($arg,10);
    else{fwrite(STDERR,"Unknown argument; only --storage and --compare supported.\n");exit(2);}
}
if(empty($options['storage'])){
    fwrite(STDERR,"Usage: php bin/restore-verification.php --storage=/absolute/staging/storage [--compare=/private/backup-inventory.json]\n");
    exit(2);
}
try{
    $root=$options['storage'];
    if(!str_starts_with($root,'/'))throw new RuntimeException('Use an absolute storage path');
    $current=RestoreInventory::snapshot($root);
    if(!isset($options['compare'])){
        echo json_encode($current,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
        exit(0);
    }
    $baselineFile=realpath($options['compare']);
    if(!$baselineFile||!is_file($baselineFile))throw new RuntimeException('Backup inventory file unavailable');
    $baseline=json_decode((string)file_get_contents($baselineFile),true,512,JSON_THROW_ON_ERROR);
    if(!is_array($baseline))throw new RuntimeException('Backup inventory is not valid');
    $result=RestoreInventory::compare($baseline,$current);
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit($result['match']?0:1);
}catch(Throwable $e){
    // No local paths or user data in error output.
    fwrite(STDERR,"Restore inventory could not complete: verify the input paths and file permissions.\n");
    exit(2);
}
