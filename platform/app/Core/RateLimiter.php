<?php
namespace Digiti\Core;
final class RateLimiter
{
    public static function allow(string $bucket,int $limit,int $windowSeconds):bool
    {
        $key='rate:'.hash('sha256',$bucket);
        if(RedisStore::enabled())try{$r=RedisStore::client();$rk=RedisStore::key($key);$n=(int)$r->incr($rk);if($n===1)$r->expire($rk,$windowSeconds);return$n<=$limit;}catch(\Throwable){}
        try{
            $hash=hash('sha256',$bucket,true);$row=Store::one('SELECT bucket_key,window_start,hit_count FROM rate_limits WHERE bucket_key=?',[$hash]);$now=time();if(!$row||strtotime($row['window_start'])<=$now-$windowSeconds){Store::exec('INSERT INTO rate_limits(bucket_key,window_start,hit_count,updated_at) VALUES(?,NOW(6),1,NOW(6)) ON DUPLICATE KEY UPDATE window_start=NOW(6),hit_count=1,updated_at=NOW(6)',[$hash]);return true;}Store::exec('UPDATE rate_limits SET hit_count=hit_count+1,updated_at=NOW(6) WHERE bucket_key=?',[$hash]);return((int)$row['hit_count']+1)<=$limit;
        }catch(\Throwable){return false;}
    }
}
