<?php
declare(strict_types=1);
namespace Digiti\Core;

/** Redis-first limiter with atomic MariaDB fallback during Redis unavailability. */
final class RateLimiter
{
    public static function allow(string $bucket,int $limit,int $windowSeconds):bool
    {
        if($limit<1)return false;
        $windowSeconds=max(1,$windowSeconds);
        $key='rate:'.hash('sha256',$bucket);
        if(RedisStore::enabled())try{
            $redis=RedisStore::client();$redisKey=RedisStore::key($key);
            $count=(int)$redis->incr($redisKey);
            if($count===1)$redis->expire($redisKey,$windowSeconds);
            return $count<=$limit;
        }catch(\Throwable){}
        try {
            $hash=hash('sha256',$bucket,true);
            $window=(int)$windowSeconds; // integer interpolation, never caller SQL
            // One atomic upsert replaces the previous read-then-write race.
            // A later concurrent SELECT may be conservatively over limit; it cannot approve excess traffic.
            Store::exec(
                "INSERT INTO rate_limits(bucket_key,window_start,hit_count,updated_at)
                 VALUES(?,NOW(6),1,NOW(6))
                 ON DUPLICATE KEY UPDATE
                   hit_count=IF(window_start<=DATE_SUB(NOW(6), INTERVAL {$window} SECOND),1,hit_count+1),
                   window_start=IF(window_start<=DATE_SUB(NOW(6), INTERVAL {$window} SECOND),NOW(6),window_start),
                   updated_at=NOW(6)",
                [$hash]
            );
            return (int)Store::value('SELECT hit_count FROM rate_limits WHERE bucket_key=?',[$hash])<=$limit;
        }catch(\Throwable){
            // A broken backend must never result in unlimited sign-in attempts.
            return false;
        }
    }
}
