<?php
declare(strict_types=1);
namespace EpaperSaas\Core;

/**
 * Read-only gate for EP-DI code releases that no longer track runtime media.
 * Backups alone do not make a historical git checkout safe.
 */
final class StorageDeploymentSafety
{
    public static function inspect(string $appRoot,string $persistentRoot,array $backupInventory): array
    {
        $app=realpath($appRoot);
        $persist=realpath($persistentRoot);
        $result=[
            'ok'=>false,
            'app_valid'=>false,
            'persistent_storage_external'=>false,
            'storage_link_matches_persistent'=>false,
            'storage_data_matches_backup'=>false,
            'deployment_driver_verified'=>false,
            'credentials_rotated_verified'=>false,
            'production_deployment_approved'=>false,
        ];
        if($app===false||!is_dir($app)
            ||!is_file($app.'/app/bootstrap.php')
            ||!is_file($app.'/public_html/index.php'))return $result;
        $result['app_valid']=true;
        if($persist===false||!is_dir($persist))return $result;
        $prefix=rtrim($app,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if($persist===$app||str_starts_with($persist,$prefix))return $result;
        $public=realpath($app.'/public_html');
        if($public!==false&&($persist===$public||str_starts_with($persist,rtrim($public,'/').'/')))return $result;
        $result['persistent_storage_external']=true;
        $storage=$app.'/storage';
        if(!is_link($storage)||realpath($storage)!==$persist)return $result;
        $result['storage_link_matches_persistent']=true;
        try{
            $restored=RestoreInventory::snapshot($persist);
            $check=RestoreInventory::compare($backupInventory,$restored);
            $result['storage_data_matches_backup']=$check['match']===true;
            $result['media_counts']=[
                'media'=>(int)($restored['categories']['media']['files']??0),
                'uploads'=>(int)($restored['categories']['uploads']['files']??0),
                'clips'=>(int)($restored['categories']['clips']['files']??0),
            ];
        }catch(\Throwable){return $result;}
        $result['ok']=$result['app_valid']
            &&$result['persistent_storage_external']
            &&$result['storage_link_matches_persistent']
            &&$result['storage_data_matches_backup'];
        return $result;
    }
}
