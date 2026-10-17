<?php
declare(strict_types=1);
namespace EpaperSaas\Core {
    class Uuid {public static function isValid(string $v):bool{return (bool)preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i',$v);}}
    class Config {public static function get(string $k,mixed $d=null):mixed{return $d;}}
    class Http {}
    class RedisStore {
        public static array $evicted=[];
        public static function enabled():bool{return false;}
        public static function del(string $k):void{self::$evicted[]=$k;}
    }
    class Store {
        public static array $states=[];
        public static function tx(callable $fn):mixed{return $fn(null);}
        public static function one(string $sql,array $args=[]):?array{return isset(self::$states[$args[0]])?['state_value'=>self::$states[$args[0]]]:null;}
        public static function exec(string $sql,array $args=[]):int{self::$states[$args[0]]=$args[1];return 1;}
    }
}
namespace EpaperSaas\Services {
    class DomainResolver {public static array $files=[];public static function invalidateFileCache(string $key):void{self::$files[]=$key;}}
    require dirname(__DIR__).'/app/Services/PlatformInvalidation.php';
}
namespace {
    use EpaperSaas\Services\{PlatformInvalidation,DomainResolver};
    use EpaperSaas\Core\{RedisStore,Store};
    $errors=[];
    $check=static function(bool $ok,string $label)use(&$errors):void{
        echo ($ok?'PASS':'FAIL')." — $label\n";if(!$ok)$errors[]=$label;
    };
    $secret=str_repeat('z',40);$now=time();
    $event='01234567-89ab-4cde-8123-0123456789ab';
    $tenant='01234567-89ab-4cde-8123-0123456789ac';
    $message=['event_id'=>$event,'tenant_guid'=>$tenant,'hostnames'=>['paper.example.org'],'kind'=>'control_plane.changed'];
    $raw=json_encode($message,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $time=(string)$now;$signed='v1='.hash_hmac('sha256',$time."\n".$raw,$secret);
    $body=PlatformInvalidation::verify($raw,$time,$signed,$secret,$now);
    $check($body['event_id']===$event&&$body['tenant_guid']===$tenant,'verified authentic event identity');
    $check($body['hostnames']===['paper.example.org'],'verified hostname');
    $check(PlatformInvalidation::apply($body,$raw)===true,'first event applied');
    $check(in_array('domain:v2:paper.example.org',RedisStore::$evicted,true),'domain Redis cache invalidated');
    $check(in_array('tenant-meta:v2:'.$tenant,RedisStore::$evicted,true),'tenant metadata cache invalidated');
    $check(in_array('access:v2:'.$tenant,RedisStore::$evicted,true),'service-access cache invalidated');
    $check(in_array('tenant:'.$tenant.':entitlements',RedisStore::$evicted,true),'entitlements cache invalidated');
    $check(in_array('domain:v2:paper.example.org',DomainResolver::$files,true),'domain disk cache invalidated');
    $before=count(RedisStore::$evicted);
    $check(PlatformInvalidation::apply($body,$raw)===false,'duplicate event idempotently acknowledged');
    $check(count(RedisStore::$evicted)===$before,'duplicate does not evict again');
    $altered=$message;$altered['hostnames']=['other.example.org'];
    try{PlatformInvalidation::apply($body,json_encode($altered,JSON_THROW_ON_ERROR));$conflict=false;}
    catch(DomainException $e){$conflict=$e->getMessage()==='REPLAY_CONFLICT';}
    $check($conflict,'reused event ID with different payload rejected');
    $reject=static function(string $raw,string $t,string $sig,string $key,int $now):bool{
        try{PlatformInvalidation::verify($raw,$t,$sig,$key,$now);return false;}catch(Throwable){return true;}
    };
    $check($reject($raw,(string)($now-301),'v1='.hash_hmac('sha256',($now-301)."\n".$raw,$secret),$secret,$now),'expired signature rejected');
    $check($reject($raw,$time,'v1='.str_repeat('0',64),$secret,$now),'forged HMAC rejected');
    $check($reject($raw,$time,$signed,'too-short',$now),'missing/weak signing secret rejected');
    $check($reject($raw.' ',$time,$signed,$secret,$now),'altered signed body rejected');
    $x=$message;$x['hostnames']=['../etc'];
    $body2=json_encode($x,JSON_THROW_ON_ERROR);$sig2='v1='.hash_hmac('sha256',$time."\n".$body2,$secret);
    $check($reject($body2,$time,$sig2,$secret,$now),'invalid hostname rejected');
    exit($errors?1:0);
}
