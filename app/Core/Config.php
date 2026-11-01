<?php
namespace EpaperSaas\Core;
final class Config
{
    private static array $data=[];
    public static function load(string $root): void
    {
        $file=$root.'/config/config.php';if(!is_file($file))throw new \RuntimeException('ePaper SaaS is not configured. Open /install.php.');$d=require $file;if(!is_array($d))throw new \RuntimeException('Invalid config.php');$runtime=$root.'/config/runtime.php';if(is_file($runtime)){$r=require $runtime;if(!is_array($r))throw new \RuntimeException('Invalid runtime.php');$d=self::merge($d,$r);}self::$data=$d;date_default_timezone_set((string)self::get('app.timezone','Asia/Kolkata'));
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

