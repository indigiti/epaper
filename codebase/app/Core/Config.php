<?php
namespace EpaperSaas\Core;
final class Config
{
    private static array $data=[];
    public static function load(string $root): void
    {
        $privateDir=trim((string)(getenv('EPDI_PRIVATE_CONFIG_DIR')?:''));
        if($privateDir!==''){
            $candidate=realpath($privateDir);
            if(!$candidate||!is_dir($candidate)||!str_starts_with($candidate,DIRECTORY_SEPARATOR))throw new \RuntimeException('Invalid private configuration directory');
            $webroot=realpath($root.'/public_html');
            if($webroot&&($candidate===$webroot||str_starts_with($candidate,$webroot.DIRECTORY_SEPARATOR)))throw new \RuntimeException('Private configuration must not be web accessible');
            $configDir=$candidate;
        }else{$configDir=$root.'/config';}
        $file=$configDir.'/config.php';
        if(!is_file($file)||!is_readable($file))throw new \RuntimeException('ePaper SaaS runtime configuration is missing');
        $d=require $file;if(!is_array($d))throw new \RuntimeException('Invalid config.php');$runtime=$configDir.'/runtime.php';if(is_file($runtime)){$r=require $runtime;if(!is_array($r))throw new \RuntimeException('Invalid runtime.php');$d=self::merge($d,$r);}self::$data=$d;date_default_timezone_set((string)self::get('app.timezone','Asia/Kolkata'));
    }
    public static function get(string $key,mixed $default=null): mixed
    {
        $v=self::$data;foreach(explode('.',$key) as$s){if(!is_array($v)||!array_key_exists($s,$v))return $default;$v=$v[$s];}return $v;
    }
    public static function all(): array{return self::$data;}
    private static function merge(array $base,array $override): array
    {
        foreach($override as $k=>$v){if(is_array($v)&&isset($base[$k])&&is_array($base[$k]))$base[$k]=self::merge($base[$k],$v);else$base[$k]=$v;}return $base;
    }
}

