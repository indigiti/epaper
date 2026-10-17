<?php
namespace Digiti\Core;

final class RedisStore
{
    private static ?\Redis $redis = null;
    public static function enabled(): bool { return (bool)Config::get('redis.enabled', false) && class_exists('Redis'); }
    public static function client(): ?\Redis
    {
        if (!self::enabled()) return null;
        if (self::$redis) return self::$redis;
        $r = new \Redis();
        $r->connect((string)Config::get('redis.host','127.0.0.1'), (int)Config::get('redis.port',6379), 1.5);
        $u=Config::get('redis.username'); $p=Config::get('redis.password');
        if ($p) $r->auth($u ? [$u,$p] : $p);
        $r->select((int)Config::get('redis.database',0));
        self::$redis=$r; return $r;
    }
    public static function key(string $suffix): string { return (string)Config::get('redis.prefix','digiti:prod:platform:') . $suffix; }
    public static function getJson(string $key): ?array
    {
        try { $raw=self::client()?->get(self::key($key)); return $raw ? json_decode($raw,true) : null; } catch (\Throwable) { return null; }
    }
    public static function setJson(string $key,array $value,int $ttl=600): void
    {
        try { self::client()?->setex(self::key($key),$ttl,json_encode($value,JSON_UNESCAPED_SLASHES)); } catch (\Throwable) {}
    }
    public static function del(string $key): void { try { self::client()?->del(self::key($key)); } catch (\Throwable) {} }
}
