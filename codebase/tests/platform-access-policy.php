<?php
declare(strict_types=1);
// Isolated behavioural test: stub network/cache dependencies, never contact live Platform.
namespace EpaperSaas\Core {
    class Config {
        public static function get(string $key,mixed $default=null): mixed {
            return $key==='platform.access_cache_ttl'?10:$default;
        }
    }
    class RequestContext {
        public static function tenantGuid(): string {return '00000000-0000-4000-8000-000000000001';}
    }
    class RedisStore {
        public static ?array $cached=null;
        public static array $writes=[];
        public static function getJson(string $key): ?array {return self::$cached;}
        public static function setJson(string $key,array $value,int $ttl): void {self::$writes[]=[$key,$value,$ttl];self::$cached=$value;}
    }
}
namespace EpaperSaas\Services {
    class PlatformClient {
        public static array $response=['ok'=>true,'service_access_state'=>'FULL'];
        public static bool $fail=false;
        public static int $reads=0;
        public static function instance(): self {return new self();}
        public function access(string $tenant): array {
            self::$reads++;
            if(self::$fail)throw new \RuntimeException('Platform unavailable');
            return self::$response;
        }
    }
    require dirname(__DIR__).'/app/Services/PlatformAccess.php';
}
namespace {
    use EpaperSaas\Services\{PlatformAccess,PlatformClient};
    use EpaperSaas\Core\RedisStore;
    $errors=[];
    $resetMemo=static function():void {
        (new \ReflectionClass(PlatformAccess::class))->getProperty('memo')->setValue(null,[]);
    };
    $check=static function(bool $ok,string $label)use(&$errors):void{echo ($ok?'PASS':'FAIL')." — $label\n";if(!$ok)$errors[]=$label;};

    $check(PlatformAccess::verifiedState(['ok'=>true,'service_access_state'=>'FULL'])==='FULL','signed-in active state permitted');
    $check(PlatformAccess::verifiedState(['ok'=>false,'service_access_state'=>'FULL'])==='BLOCKED','failed response cannot grant');
    $check(PlatformAccess::verifiedState(['ok'=>true])==='BLOCKED','missing access state denied');
    $check(PlatformAccess::verifiedState(['ok'=>true,'service_access_state'=>'SOMETHING'])==='BLOCKED','invalid access state denied');

    RedisStore::$cached=['service_access_state'=>'FULL'];
    PlatformClient::$response=['ok'=>true,'service_access_state'=>'BLOCKED'];
    $check(PlatformAccess::currentState()==='BLOCKED','legacy cached FULL ignored');
    $check(PlatformClient::$reads===1,'first authoritative check executed');
    $check(PlatformAccess::currentState()==='BLOCKED','fresh v2 deny reused');
    $check(PlatformClient::$reads===1,'fresh bounded cache avoids redundant external call');
    $check((RedisStore::$writes[0][2]??0)===10,'cached access TTL capped at configured 10s');
    $check(str_starts_with(RedisStore::$writes[0][0]??'','access:v2:'),'new cache namespace avoids legacy keys');

    $resetMemo();
    RedisStore::$cached=['_format'=>'access-v2','state'=>'FULL','valid_until'=>time()-1];
    PlatformClient::$fail=true;
    $check(PlatformAccess::currentState()==='UNAVAILABLE','expired grant and Platform failure fail closed');
    $resetMemo();
    RedisStore::$cached=null;
    $check(PlatformAccess::currentState()==='UNAVAILABLE','no cached grant and Platform failure fail closed');
    $resetMemo();
    RedisStore::$cached=['_format'=>'access-v2','state'=>'INVALID','valid_until'=>time()+10];
    $check(PlatformAccess::currentState()==='UNAVAILABLE','tampered cached state not granted');
    exit($errors?1:0);
}
