<?php
declare(strict_types=1);
namespace EpaperSaas\Core;

/** Guard exposed installer independently of optional Composer/bootstrap services. */
final class InstallerGuard
{
    public static function installed(string $root): bool
    {
        if(is_file($root.'/storage/installed.lock')||is_file($root.'/config/config.php'))return true;
        $external=trim((string)(getenv('EPDI_CONFIG_PATH') ?: ''));
        return $external!==''&&is_file($external);
    }

    public static function authorized(string $root,string $provided): bool
    {
        $token=trim((string)(getenv('EPDI_INSTALL_TOKEN') ?: ''));
        $path=$root.'/config/install.token';
        if($token===''&&is_file($path)){
            $read=@file_get_contents($path);
            if(is_string($read))$token=trim($read);
        }
        return strlen($token)>=32&&hash_equals($token,$provided);
    }
}
