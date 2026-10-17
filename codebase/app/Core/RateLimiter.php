<?php
declare(strict_types=1);
namespace EpaperSaas\Core;

/** Atomic Redis throttling, with a cross-session file lock fallback. */
final class RateLimiter
{
    public static function allow(string $bucket,int $limit,int $seconds): bool
    {
        if($bucket===''||$limit<1||$seconds<1)return false;
        try{
            $client=RedisStore::client();
            if($client){
                $lua='local n=redis.call("INCR",KEYS[1]); if n==1 then redis.call("EXPIRE",KEYS[1],ARGV[1]); end; return n';
                $n=$client->eval($lua,[RedisStore::key('rate:'.$bucket),$seconds],1);
                if(is_numeric($n))return (int)$n<=$limit;
                // An unusable Redis backend must never silently allow a request.
            }
        }catch(\Throwable){}
        return self::fileAllow($bucket,$limit,$seconds);
    }

    private static function directory(): string
    {
        $override=trim((string)Config::get('security.rate_limit_storage_dir',''));
        return $override!==''?$override:dirname(__DIR__,2).'/storage/cache/rate-limits';
    }

    private static function fileAllow(string $bucket,int $limit,int $seconds): bool
    {
        $dir=self::directory();
        if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))return false;
        if(!is_writable($dir))return false;
        $file=rtrim($dir,'/').'/'.hash('sha256',RedisStore::key('rate:'.$bucket)).'.json';
        $fp=@fopen($file,'c+');
        if(!is_resource($fp))return false;
        try{
            @chmod($file,0600);
            if(!@flock($fp,LOCK_EX))return false;
            $raw=stream_get_contents($fp);
            if(!is_string($raw))return false;
            $state=json_decode($raw,true);
            $now=time();
            $until=is_array($state)?(int)($state['until']??0):0;
            $count=$until>$now?(int)($state['count']??0):0;
            if($until<=$now)$until=$now+$seconds;
            $count++;
            $new=json_encode(['count'=>$count,'until'=>$until]);
            if(!is_string($new)||!rewind($fp)||!ftruncate($fp,0))return false;
            if(fwrite($fp,$new)!==strlen($new)||!fflush($fp))return false;
            return $count<=$limit;
        }catch(\Throwable){return false;}
        finally{@flock($fp,LOCK_UN);fclose($fp);}
    }

    /** Bounded cleanup, with one-hour grace for expired counters. */
    public static function pruneExpired(int $maxFiles=200): int
    {
        $dir=self::directory();
        if(!is_dir($dir))return 0;
        $removed=0;$scanned=0;$now=time();
        try{
            foreach(new \FilesystemIterator($dir,\FilesystemIterator::SKIP_DOTS) as $entry){
                if(++$scanned>max(1,$maxFiles))break;
                if(!$entry->isFile()||!preg_match('/^[a-f0-9]{64}\.json$/D',$entry->getFilename()))continue;
                if($entry->getMTime()>$now-3600)continue;
                $path=$entry->getPathname();
                $fp=@fopen($path,'r+');
                if(!is_resource($fp))continue;
                try{
                    if(!@flock($fp,LOCK_EX|LOCK_NB))continue;
                    $raw=stream_get_contents($fp);
                    $data=is_string($raw)?json_decode($raw,true):null;
                    if(is_array($data)&&(int)($data['until']??0)<$now-3600&&@unlink($path))$removed++;
                }finally{@flock($fp,LOCK_UN);fclose($fp);}
            }
        }catch(\Throwable){}
        return $removed;
    }
}
