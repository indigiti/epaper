<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,RedisStore,RequestContext};

/**
 * EP-DI v2.0.0-rc.1 shared read cache.
 *
 * L0 request memory -> L1 Redis -> L2 atomic filesystem -> loader (usually MariaDB).
 * Entries have a fresh window and a stale-while-revalidate window. Stale values
 * are served immediately while one lock holder refreshes after the response.
 */
final class MultiLayerCache
{
    /** @var array<string,array<string,mixed>> */
    private static array $memory=[];
    private static array $metrics=['memory_hits'=>0,'redis_hits'=>0,'file_hits'=>0,'stale_hits'=>0,'misses'=>0,'loads'=>0,'writes'=>0,'lock_contention'=>0,'errors'=>0];

    public static function remember(string $key,int $freshTtl,int $staleTtl,callable $loader,array $options=[]): mixed
    {
        $freshTtl=max(1,$freshTtl);$staleTtl=max($freshTtl,$staleTtl);
        $full=self::scopedKey($key);
        if(isset(self::$memory[$full])){
            self::$metrics['memory_hits']++;
            return self::$memory[$full]['value'];
        }

        $now=time();$redisKey='mlc:v3:'.$full;
        $env=RedisStore::getJson($redisKey);
        if(self::validEnvelope($env,$now)){
            self::$metrics['redis_hits']++;
            self::$memory[$full]=$env;
            return $env['value'];
        }
        if(self::staleEnvelope($env,$now)){
            self::$metrics['redis_hits']++;self::$metrics['stale_hits']++;
            self::$memory[$full]=$env;
            self::scheduleRefresh($full,$redisKey,$freshTtl,$staleTtl,$loader,$options);
            return $env['value'];
        }

        $file=self::readFile($full);
        if(self::validEnvelope($file,$now)){
            self::$metrics['file_hits']++;
            self::$memory[$full]=$file;
            RedisStore::setJson($redisKey,$file,max(1,(int)$file['stale_until']-$now));
            return $file['value'];
        }
        if(self::staleEnvelope($file,$now)){
            self::$metrics['file_hits']++;self::$metrics['stale_hits']++;
            self::$memory[$full]=$file;
            RedisStore::setJson($redisKey,$file,max(1,(int)$file['stale_until']-$now));
            self::scheduleRefresh($full,$redisKey,$freshTtl,$staleTtl,$loader,$options);
            return $file['value'];
        }

        self::$metrics['misses']++;
        return self::loadAndStore($full,$redisKey,$freshTtl,$staleTtl,$loader,$options);
    }

    public static function put(string $key,mixed $value,int $freshTtl=300,int $staleTtl=1800): void
    {
        $full=self::scopedKey($key);$redisKey='mlc:v3:'.$full;
        self::storeEnvelope($full,$redisKey,$value,$freshTtl,$staleTtl);
    }

    public static function forget(string $key): void
    {
        $full=self::scopedKey($key);unset(self::$memory[$full]);RedisStore::del('mlc:v3:'.$full);$path=self::filePath($full);if(is_file($path))@unlink($path);
    }

    public static function purgeNamespace(string $prefix): int
    {
        $tenant=RequestContext::tenantGuid();$needle=$tenant.':'.$prefix;$deleted=RedisStore::deleteByPrefix('mlc:v3:'.$needle);
        foreach(array_keys(self::$memory) as$k)if(str_starts_with($k,$needle))unset(self::$memory[$k]);
        $dir=self::root();if(is_dir($dir))foreach(glob($dir.'/*.cache')?:[] as$f){$raw=@file_get_contents($f);$env=$raw?json_decode($raw,true):null;if(is_array($env)&&str_starts_with((string)($env['_key']??''),$needle)){@unlink($f);$deleted++;}}
        return$deleted;
    }

    public static function cleanup(int $maxAge=172800): array
    {
        $now=time();$files=0;$bytes=0;$removed=0;$removedBytes=0;$dir=self::root();
        if(is_dir($dir))foreach(glob($dir.'/*.cache')?:[] as$f){$files++;$sz=(int)@filesize($f);$bytes+=$sz;$raw=@file_get_contents($f);$env=$raw?json_decode($raw,true):null;$expired=!is_array($env)||((int)($env['stale_until']??0)>0&&(int)$env['stale_until']<$now)||(@filemtime($f)<$now-$maxAge);if($expired&&@unlink($f)){$removed++;$removedBytes+=$sz;}}
        foreach(glob($dir.'/*.tmp.*')?:[] as$f)if(@filemtime($f)<$now-3600)@unlink($f);
        foreach(glob($dir.'/*.lock')?:[] as$f)if(@filemtime($f)<$now-3600)@unlink($f);
        return compact('files','bytes','removed','removedBytes');
    }

    public static function metrics(): array{return self::$metrics;}

    public static function filesystemStats(): array
    {
        $files=0;$bytes=0;$dir=self::root();if(is_dir($dir))foreach(glob($dir.'/*.cache')?:[] as$f){$files++;$bytes+=(int)@filesize($f);}return['files'=>$files,'bytes'=>$bytes,'path'=>$dir];
    }

    private static function loadAndStore(string $full,string $redisKey,int $freshTtl,int $staleTtl,callable $loader,array $options): mixed
    {
        $lockKey='cache-rebuild:'.hash('sha256',$full);$redisToken=null;$fileLock=null;
        if(RedisStore::enabled()){
            $redisToken=RedisStore::acquireLock($lockKey,15);
            if($redisToken===null){
                $connected=(bool)(RedisStore::serverMetrics()['connected']??false);
                if($connected){
                    self::$metrics['lock_contention']++;
                    // Another request owns the shared rebuild lock. Give it a short
                    // opportunity to populate L1/L2 before allowing a last-resort load.
                    for($i=0;$i<8;$i++){
                        usleep(50000);
                        $retry=RedisStore::getJson($redisKey)?:self::readFile($full);
                        if(is_array($retry)&&array_key_exists('value',$retry)){self::$memory[$full]=$retry;return$retry['value'];}
                        $redisToken=RedisStore::acquireLock($lockKey,15);
                        if($redisToken!==null)break;
                    }
                }else{$fileLock=self::fileLock($full);}
            }
        }else{$fileLock=self::fileLock($full);}
        if($redisToken===null&&!is_resource($fileLock)){
            // Degraded Redis or a longer-running lock owner: recheck both cache
            // layers once more before the bounded last-resort rebuild below.
            self::$metrics['lock_contention']++;
            usleep(50000);
            $retry=RedisStore::getJson($redisKey)?:self::readFile($full);if(is_array($retry)&&array_key_exists('value',$retry)){self::$memory[$full]=$retry;return$retry['value'];}
        }
        try{
            self::$metrics['loads']++;$value=$loader();self::storeEnvelope($full,$redisKey,$value,$freshTtl,$staleTtl);return$value;
        }catch(\Throwable$e){self::$metrics['errors']++;throw$e;}finally{
            if($redisToken!==null)RedisStore::releaseLock($lockKey,$redisToken);
            if(is_resource($fileLock)){@flock($fileLock,LOCK_UN);@fclose($fileLock);}
        }
    }

    private static function scheduleRefresh(string $full,string $redisKey,int $freshTtl,int $staleTtl,callable $loader,array $options): void
    {
        if(!($options['swr']??true))return;
        static $scheduled=[];if(isset($scheduled[$full]))return;$scheduled[$full]=true;
        register_shutdown_function(static function()use($full,$redisKey,$freshTtl,$staleTtl,$loader):void{
            try{
                if(function_exists('fastcgi_finish_request'))@fastcgi_finish_request();
                $lockKey='cache-rebuild:'.hash('sha256',$full);$redisToken=null;$fileLock=null;
                if(RedisStore::enabled()){
                    $redisToken=RedisStore::acquireLock($lockKey,15);
                    if($redisToken===null){
                        $connected=(bool)(RedisStore::serverMetrics()['connected']??false);
                        if($connected)return; // another node/request is refreshing
                        $fileLock=self::fileLock($full);
                    }
                }else{$fileLock=self::fileLock($full);}
                if($redisToken===null&&!is_resource($fileLock))return;
                try{$value=$loader();self::storeEnvelope($full,$redisKey,$value,$freshTtl,$staleTtl);}finally{
                    if($redisToken!==null)RedisStore::releaseLock($lockKey,$redisToken);
                    if(is_resource($fileLock)){@flock($fileLock,LOCK_UN);@fclose($fileLock);}
                }
            }catch(\Throwable){}
        });
    }

    private static function storeEnvelope(string $full,string $redisKey,mixed $value,int $freshTtl,int $staleTtl): void
    {
        $now=time();$jitter=min(30,max(0,(int)floor($freshTtl*.1)));$fresh=$now+\EpaperSaas\Core\RedisStore::ttlWithJitter($freshTtl,$jitter);$stale=max($fresh+1,$now+$staleTtl);
        $env=['_key'=>$full,'generated_at'=>gmdate('c'),'fresh_until'=>$fresh,'stale_until'=>$stale,'value'=>$value];
        self::$memory[$full]=$env;RedisStore::setJson($redisKey,$env,max(1,$stale-$now));self::writeFile($full,$env);self::$metrics['writes']++;
    }

    private static function validEnvelope(?array $env,int $now): bool{return is_array($env)&&array_key_exists('value',$env)&&(int)($env['fresh_until']??0)>=$now;}
    private static function staleEnvelope(?array $env,int $now): bool{return is_array($env)&&array_key_exists('value',$env)&&(int)($env['fresh_until']??0)<$now&&(int)($env['stale_until']??0)>=$now;}
    private static function scopedKey(string $key): string{return RequestContext::tenantGuid().':'.ltrim($key,':');}
    private static function root(): string{$p=dirname(__DIR__,2).'/storage/cache/runtime';if(!is_dir($p))@mkdir($p,0775,true);return$p;}
    private static function filePath(string $full): string{return self::root().'/'.hash('sha256',$full).'.cache';}
    private static function readFile(string $full): ?array{$f=self::filePath($full);if(!is_file($f))return null;$raw=@file_get_contents($f);$d=$raw?json_decode($raw,true):null;return is_array($d)&&($d['_key']??'')===$full?$d:null;}
    private static function writeFile(string $full,array $env): void{$path=self::filePath($full);$tmp=$path.'.tmp.'.bin2hex(random_bytes(4));$json=json_encode($env,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);if($json===false)return;if(@file_put_contents($tmp,$json,LOCK_EX)!==false)@rename($tmp,$path);else@unlink($tmp);}
    private static function fileLock(string $full){$f=@fopen(self::filePath($full).'.lock','c');if(!$f)return null;if(!@flock($f,LOCK_EX|LOCK_NB)){@fclose($f);return null;}return$f;}
}
