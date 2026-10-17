<?php
namespace EpaperSaas\Core;
final class RateLimiter
{
    public static function allow(string $bucket,int $limit,int $seconds): bool
    {
        try{$r=RedisStore::client();if($r){$k=RedisStore::key('rate:'.$bucket);$n=$r->incr($k);if($n===1)$r->expire($k,$seconds);return $n<=$limit;}}catch(\Throwable){}
        Auth::start();$k='_rl_'.hash('sha256',$bucket);$now=time();$v=$_SESSION[$k]??['n'=>0,'until'=>$now+$seconds];if(($v['until']??0)<$now)$v=['n'=>0,'until'=>$now+$seconds];$v['n']++;$_SESSION[$k]=$v;return$v['n']<=$limit;
    }
}
