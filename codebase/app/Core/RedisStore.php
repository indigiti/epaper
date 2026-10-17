<?php
namespace EpaperSaas\Core;

final class RedisStore
{
    private static ?\Redis $client = null;
    private static int $reads = 0;
    private static int $hits = 0;
    private static int $misses = 0;
    private static int $writes = 0;
    private static int $errors = 0;

    public static function enabled(): bool
    {
        return (bool) Config::get('redis.enabled', false) && class_exists('Redis');
    }

    public static function client(): ?\Redis
    {
        if (!self::enabled()) return null;
        if (self::$client) return self::$client;

        $r = new \Redis();
        $r->connect((string) Config::get('redis.host', '127.0.0.1'), (int) Config::get('redis.port', 6379), 1.5);
        $u = Config::get('redis.username');
        $p = Config::get('redis.password');
        if ($p) $r->auth($u ? [$u, $p] : $p);
        $r->select((int) Config::get('redis.database', 0));
        self::$client = $r;
        return $r;
    }

    public static function key(string $suffix): string
    {
        return (string) Config::get('redis.prefix', 'digiti:prod:epaper:') . $suffix;
    }

    public static function getJson(string $key): ?array
    {
        self::$reads++;
        try {
            $raw = self::client()?->get(self::key($key));
            if ($raw === false || $raw === null || $raw === '') {
                self::$misses++;
                return null;
            }
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                self::$misses++;
                return null;
            }
            self::$hits++;
            return $decoded;
        } catch (\Throwable) {
            self::$errors++;
            self::$misses++;
            return null;
        }
    }

    public static function setJson(string $key, array $value, int $ttl = 600): void
    {
        self::$writes++;
        try {
            $client = self::client();
            if (!$client) return;
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($ttl > 0) $client->setex(self::key($key), $ttl, $json);
            else $client->set(self::key($key), $json);
        } catch (\Throwable) {
            self::$errors++;
        }
    }

    public static function get(string $key): ?string
    {
        self::$reads++;
        try {
            $raw = self::client()?->get(self::key($key));
            if ($raw === false || $raw === null) {
                self::$misses++;
                return null;
            }
            self::$hits++;
            return (string) $raw;
        } catch (\Throwable) {
            self::$errors++;
            self::$misses++;
            return null;
        }
    }

    public static function set(string $key, string $value, int $ttl = 600): void
    {
        self::$writes++;
        try {
            $client = self::client();
            if (!$client) return;
            if ($ttl > 0) $client->setex(self::key($key), $ttl, $value);
            else $client->set(self::key($key), $value);
        } catch (\Throwable) {
            self::$errors++;
        }
    }

    public static function del(string $key): void
    {
        try {
            self::client()?->del(self::key($key));
        } catch (\Throwable) {
            self::$errors++;
        }
    }

    /** Acquire a token-owned Redis lock. Null means contended or Redis unavailable. */
    public static function acquireLock(string $key, int $ttl = 60): ?string
    {
        try {
            $client = self::client();
            if (!$client) return null;
            $token = Uuid::v7();
            $ok = $client->set(self::key('lock:' . $key), $token, ['nx', 'ex' => max(1, $ttl)]);
            return $ok ? $token : null;
        } catch (\Throwable) {
            self::$errors++;
            return null;
        }
    }

    /** Release only the lock owned by the supplied token. */
    public static function releaseLock(string $key, string $token): void
    {
        if ($token === '') return;
        try {
            $client = self::client();
            if (!$client) return;
            $lua = 'if redis.call("get", KEYS[1]) == ARGV[1] then return redis.call("del", KEYS[1]) else return 0 end';
            $client->eval($lua, [self::key('lock:' . $key), $token], 1);
        } catch (\Throwable) {
            self::$errors++;
        }
    }

    /** Backward-compatible boolean lock helper. */
    public static function lock(string $key, int $ttl = 60): bool
    {
        return self::acquireLock($key, $ttl) !== null;
    }

    /** Return decoded JSON values for namespaced keys matching a suffix prefix using SCAN. */
    public static function scanJson(string $suffixPrefix, int $limit = 500): array
    {
        $out = [];
        try {
            $client = self::client();
            if (!$client) return $out;
            $prefix = self::key($suffixPrefix);
            $it = null;
            do {
                $keys = $client->scan($it, $prefix . '*', 100);
                if ($keys === false) continue;
                foreach ($keys as $fullKey) {
                    if (count($out) >= $limit) break 2;
                    $raw = $client->get($fullKey);
                    if ($raw === false || $raw === null || $raw === '') continue;
                    $decoded = json_decode((string)$raw, true);
                    if (is_array($decoded)) $out[(string)$fullKey] = $decoded;
                }
            } while ($it !== 0 && $it !== '0');
        } catch (\Throwable) { self::$errors++; }
        return $out;
    }

    /** Delete all keys below a namespaced suffix prefix without using blocking KEYS. */
    public static function deleteByPrefix(string $suffixPrefix, int $limit = 5000): int
    {
        $deleted = 0;
        try {
            $client = self::client();
            if (!$client) return 0;
            $prefix = self::key($suffixPrefix);
            $it = null;
            do {
                $keys = $client->scan($it, $prefix . '*', 200);
                if ($keys === false || !$keys) continue;
                if ($deleted + count($keys) > $limit) $keys = array_slice($keys, 0, max(0, $limit - $deleted));
                if ($keys) $deleted += (int)$client->del($keys);
                if ($deleted >= $limit) break;
            } while ($it !== 0 && $it !== '0');
        } catch (\Throwable) { self::$errors++; }
        return $deleted;
    }

    public static function ttlWithJitter(int $ttl, int $jitter = 0): int
    {
        if ($ttl <= 0 || $jitter <= 0) return max(0, $ttl);
        $jitter = min($jitter, max(1, $ttl - 1));
        try {
            return max(1, $ttl + random_int(-$jitter, $jitter));
        } catch (\Throwable) {
            return $ttl;
        }
    }


    public static function serverMetrics(): array
    {
        $out=['connected'=>false,'used_memory'=>0,'used_memory_human'=>null,'maxmemory'=>0,'maxmemory_human'=>null,'memory_percent'=>null,'maxmemory_policy'=>null,'mem_fragmentation_ratio'=>null,'keyspace_hits'=>0,'keyspace_misses'=>0,'hit_rate'=>null,'keys'=>0,'database'=>(int)Config::get('redis.database',0),'prefix'=>(string)Config::get('redis.prefix','digiti:prod:epaper:'),'namespace_keys'=>0,'namespace_bytes'=>0,'namespace_scan_truncated'=>false,'evicted_keys'=>0,'expired_keys'=>0];
        try {
            $c=self::client();if(!$c)return$out;$out['connected']=true;
            $info=$c->info();if(is_array($info)){
                $out['used_memory']=(int)($info['used_memory']??0);$out['used_memory_human']=$info['used_memory_human']??null;$out['maxmemory']=(int)($info['maxmemory']??0);$out['maxmemory_human']=$info['maxmemory_human']??null;$out['maxmemory_policy']=$info['maxmemory_policy']??null;$out['mem_fragmentation_ratio']=isset($info['mem_fragmentation_ratio'])?(float)$info['mem_fragmentation_ratio']:null;
                if($out['maxmemory']>0)$out['memory_percent']=round(($out['used_memory']/$out['maxmemory'])*100,1);
                $out['keyspace_hits']=(int)($info['keyspace_hits']??0);$out['keyspace_misses']=(int)($info['keyspace_misses']??0);$den=$out['keyspace_hits']+$out['keyspace_misses'];$out['hit_rate']=$den>0?round(($out['keyspace_hits']/$den)*100,1):null;$out['evicted_keys']=(int)($info['evicted_keys']??0);$out['expired_keys']=(int)($info['expired_keys']??0);
            }
            $out['keys']=(int)$c->dbSize();$prefix=$out['prefix'];$it=null;$scanned=0;$limit=1000;
            do{$keys=$c->scan($it,$prefix.'*',100);if($keys===false||!$keys)continue;foreach($keys as$key){if(++$scanned>$limit){$out['namespace_scan_truncated']=true;break 2;}$out['namespace_keys']++;try{$bytes=$c->rawCommand('MEMORY','USAGE',$key);if(is_numeric($bytes))$out['namespace_bytes']+=(int)$bytes;}catch(\Throwable){}}}while($it!==0&&$it!=='0');
        } catch (\Throwable) { self::$errors++; }
        return$out;
    }

    public static function metrics(): array
    {
        $rate = self::$reads > 0 ? round((self::$hits / self::$reads) * 100, 1) : null;
        return [
            'enabled' => self::enabled(),
            'reads' => self::$reads,
            'hits' => self::$hits,
            'misses' => self::$misses,
            'writes' => self::$writes,
            'errors' => self::$errors,
            'hit_rate' => $rate,
            'driver' => self::enabled() ? 'Redis' : 'Disabled',
        ];
    }
}
