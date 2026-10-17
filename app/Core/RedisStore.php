<?php
declare(strict_types=1);
namespace Digiti\Core;

/** Optional, best-effort Redis cache: unavailable Redis cannot stall every query. */
final class RedisStore
{
    private static ?\Redis $redis=null;
    private static bool $unavailable=false;

    public static function enabled(): bool
    {
        return (bool)Config::get('redis.enabled',false)&&class_exists('Redis');
    }

    public static function client(): ?\Redis
    {
        if(!self::enabled()||self::$unavailable)return null;
        if(self::$redis)return self::$redis;
        try {
            $r=new \Redis();
            // Fail quickly on dead local Redis; configurable for remote Redis deployments.
            $timeout=max(0.1,min(1.5,(float)Config::get('redis.connect_timeout',0.5)));
            if(!$r->connect((string)Config::get('redis.host','127.0.0.1'),(int)Config::get('redis.port',6379),$timeout))
                throw new \RuntimeException('Redis connection refused');
            $user=Config::get('redis.username');$pass=Config::get('redis.password');
            if($pass&&!$r->auth($user?[$user,$pass]:$pass))
                throw new \RuntimeException('Redis authentication failed');
            if(!$r->select((int)Config::get('redis.database',0)))
                throw new \RuntimeException('Redis database selection failed');
            self::$redis=$r;
            return $r;
        }catch(\Throwable){
            self::$unavailable=true;self::$redis=null;
            return null;
        }
    }

    public static function key(string $suffix): string
    {
        return (string)Config::get('redis.prefix','digiti:prod:platform:').$suffix;
    }

    public static function getJson(string $key): ?array
    {
        try{
            $raw=self::client()?->get(self::key($key));
            $result=$raw?json_decode($raw,true):null;
            return is_array($result)?$result:null;
        }catch(\Throwable){self::$unavailable=true;self::$redis=null;return null;}
    }

    public static function setJson(string $key,array $value,int $ttl=600): void
    {
        try{
            self::client()?->setex(self::key($key),max(1,$ttl),json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        }catch(\Throwable){self::$unavailable=true;self::$redis=null;}
    }

    public static function del(string $key): void
    {
        try{self::client()?->del(self::key($key));}
        catch(\Throwable){self::$unavailable=true;self::$redis=null;}
    }
}
