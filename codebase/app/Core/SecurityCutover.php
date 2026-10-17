<?php
declare(strict_types=1);
namespace EpaperSaas\Core;

/**
 * Read-only, non-network production cutover checks.
 * Never print or store credential values, hashes, or PHP var_export dumps.
 */
final class SecurityCutover
{
    public static function inspect(string $root, ?string $configPath=null, ?string $runtimePath=null): array
    {
        $checks=[];
        $add=static function(string $name,bool $ok,string $message)use(&$checks):void {
            $checks[]=['name'=>$name,'ok'=>$ok,'detail'=>$message];
        };
        $root=realpath($root) ?: $root;
        $configPath=$configPath ?: $root.'/config/config.php';
        $externalConfig=is_string($configPath) && str_starts_with($configPath,'/');
        $configReal=realpath($configPath);
        $add('primary_config_exists',is_string($configReal)&&is_file($configReal),'Private application configuration must exist.');
        if(!$configReal||!is_file($configReal)) return self::result($checks);
        $add('primary_config_outside_checkout',!self::inside($root,$configReal),
            'Place the actual configuration file outside the application source checkout; a symlink to an external file is acceptable.');
        $add('primary_config_private',self::privateFile($configReal),
            'Configuration must not be readable or writable by other Unix users.');
        $add('primary_config_absolute',$externalConfig,'Use an absolute primary configuration path.');
        $data=self::readConfig($configReal);
        $add('primary_config_format',is_array($data),'Configuration file must return a PHP array.');
        if(!is_array($data))return self::result($checks);

        $runtimeRequested=$runtimePath!==null && trim($runtimePath)!=='';
        if($runtimeRequested) {
            $runtimeReal=realpath((string)$runtimePath);
            $add('runtime_config_exists',is_string($runtimeReal)&&is_file($runtimeReal),
                'Configured runtime override must exist at the specified path.');
            if($runtimeReal&&is_file($runtimeReal)) {
                $add('runtime_config_outside_checkout',!self::inside($root,$runtimeReal),
                    'Store runtime overrides outside the application source checkout.');
                $add('runtime_config_private',self::privateFile($runtimeReal),
                    'Runtime overrides must have private filesystem permissions.');
                $override=self::readConfig($runtimeReal);
                $add('runtime_config_format',is_array($override),'Runtime override must return an array.');
                if(is_array($override))$data=self::merge($data,$override);
            }
        }
        $app=$data['app']??[];
        $key=is_array($app)?(string)($app['key']??''):'';
        $add('application_key_strength',preg_match('/^[0-9a-f]{64,}$/i',$key)===1,
            'Require at least 64 hexadecimal characters for the signing/encryption key.');
        $add('production_environment',is_array($app)&&strtolower((string)($app['environment']??''))==='production',
            'Set app.environment to production.');
        $db=$data['db']??[];
        $add('mariadb_credentials',is_array($db)&&trim((string)($db['user']??''))!==''&&trim((string)($db['pass']??''))!=='',
            'MariaDB username and password must be configured.');
        $platform=$data['platform']??[];
        $add('platform_service_token',is_array($platform)&&trim((string)($platform['service_token']??''))!=='',
            'Digiti Platform service token must be configured.');
        $redis=$data['redis']??[];
        if(is_array($redis)&&!empty($redis['enabled'])) {
            $add('redis_credentials',trim((string)($redis['password']??''))!=='',
                'Enabled Redis must use a nonempty password.');
        }
        $queue=$data['queue']??[];
        if(is_array($queue)&&strtolower((string)($queue['driver']??''))==='rabbitmq') {
            $rabbit=$queue['rabbitmq']??[];
            $add('rabbitmq_credentials',is_array($rabbit)
                &&trim((string)($rabbit['username']??''))!==''
                &&trim((string)($rabbit['password']??''))!==''
                &&strtolower((string)($rabbit['username']??''))!=='guest',
                'RabbitMQ requires a dedicated non-guest authenticated account.');
        }
        return self::result($checks);
    }

    private static function result(array $checks): array
    {
        return ['ok'=>!in_array(false,array_column($checks,'ok'),true),'checks'=>$checks,
            'credential_rotation_verified'=>false,
            'live_backup_restore_verified'=>false,
            'git_history_remediation_verified'=>false];
    }

    private static function inside(string $root,string $file): bool
    {
        return $file===$root||str_starts_with($file,rtrim($root,'/').'/');
    }

    private static function privateFile(string $file): bool
    {
        $permissions=@fileperms($file);
        return is_int($permissions)&&($permissions & 0077)===0;
    }

    private static function readConfig(string $file): ?array
    {
        try {
            $value=(static function(string $path):mixed { return require $path; })($file);
            return is_array($value)?$value:null;
        }catch(\Throwable) { return null; }
    }

    private static function merge(array $a,array $b): array
    {
        foreach($b as $key=>$value){
            if(is_array($value)&&isset($a[$key])&&is_array($a[$key]))$a[$key]=self::merge($a[$key],$value);
            else $a[$key]=$value;
        }
        return $a;
    }
}
