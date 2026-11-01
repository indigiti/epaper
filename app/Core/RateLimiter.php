<?php
declare(strict_types=1);

namespace Digiti\Core;

final class RateLimiter
{
    public static function allow(string $bucket,int $limit,int $windowSeconds): bool
    {
        if($limit<1||$windowSeconds<1)return false;
        $key='rate:'.hash('sha256',$bucket);
        if(RedisStore::enabled()) {
            try {
                $redis=RedisStore::client();
                $rk=RedisStore::key($key);
                $n=(int)$redis->incr($rk);
                if($n===1)$redis->expire($rk,$windowSeconds);
                return $n<=$limit;
            }catch(\Throwable) {
                // SQL fallback stays atomic; no fail-open under Redis outage.
            }
        }
        try {
            $hash=hash('sha256',$bucket,true);
            // One row-level atomic upsert per request. No read/then-increment
            // race under parallel login attempts when Redis is unavailable.
            Store::exec('INSERT INTO rate_limits(bucket_key,window_start,hit_count,updated_at)
                VALUES(?,NOW(6),1,NOW(6))
                ON DUPLICATE KEY UPDATE
                    hit_count=IF(window_start<=DATE_SUB(NOW(6),INTERVAL ? SECOND),1,hit_count+1),
                    window_start=IF(window_start<=DATE_SUB(NOW(6),INTERVAL ? SECOND),NOW(6),window_start),
                    updated_at=NOW(6)',[$hash,$windowSeconds,$windowSeconds]);
            $count=(int)Store::value('SELECT hit_count FROM rate_limits WHERE bucket_key=?',[$hash]);
            return $count<=$limit;
        }catch(\Throwable){
            return false;
        }
    }
}
