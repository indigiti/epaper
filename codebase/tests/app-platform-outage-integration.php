<?php
declare(strict_types=1);
/**
 * Exercise the REAL private App::serviceAccess() method with isolated fake
 * Platform/Redis collaborators. No DB or network access, no live credentials.
 */
namespace EpaperSaas\Core {
    final class RequestContext {
        public static function tenantGuid(): string { return '11111111-1111-4111-8111-111111111111'; }
    }
    final class RedisStore {
        public static ?array $cached=null;
        public static int $writes=0;
        public static function getJson(string $key): ?array { return self::$cached; }
        public static function setJson(string $key,array $value,int $ttl): void {
            if($ttl!==180)throw new \RuntimeException('Unexpected cache TTL');
            self::$cached=$value;self::$writes++;
        }
    }
}
namespace EpaperSaas\Services {
    final class PlatformClient {
        public static string $mode='throw';
        public static int $calls=0;
        public static function instance(): self {return new self();}
        public function access(string $tenant): array {
            self::$calls++;
            if(self::$mode==='throw')throw new \RuntimeException('Simulated upstream connection failure');
            if(self::$mode==='malformed')return ['ok'=>true];
            return ['service_access_state'=>self::$mode];
        }
    }
}
namespace {
    require_once dirname(__DIR__).'/app/Services/ServiceAccessPolicy.php';
    require_once dirname(__DIR__).'/app/App.php';
    $app=new \EpaperSaas\App();
    $method=new \ReflectionMethod(\EpaperSaas\App::class,'serviceAccess');
    $cases=0;$failed=0;
    $check=function(bool $ok,string $why)use(&$cases,&$failed):void{
        $cases++;if(!$ok){fwrite(STDERR,"FAIL: $why\n");$failed++;}
    };
    $access=fn():string=>$method->invoke($app);
    $reset=function(string $mode,?array $cached=null):void{
        \EpaperSaas\Services\PlatformClient::$mode=$mode;
        \EpaperSaas\Services\PlatformClient::$calls=0;
        \EpaperSaas\Core\RedisStore::$cached=$cached;
        \EpaperSaas\Core\RedisStore::$writes=0;
    };
    $reset('throw');
    $check($access()==='UNAVAILABLE','new upstream outage fails closed');
    $check(\EpaperSaas\Core\RedisStore::$writes===0,'outage never caches FULL or UNKNOWN');
    $reset('FULL');
    $check($access()==='FULL','verified full state is accepted');
    $check(\EpaperSaas\Core\RedisStore::$writes===1,'verified state cached');
    $reset('RESTRICTED');
    $check($access()==='RESTRICTED','verified restricted state retained');
    $reset('BLOCKED');
    $check($access()==='BLOCKED','authoritative blocked status preserved');
    $reset('malformed');
    $check($access()==='UNAVAILABLE','malformed Platform response fails closed');
    $check(\EpaperSaas\Core\RedisStore::$writes===0,'invalid state never cached');
    $reset('throw',['service_access_state'=>'RESTRICTED']);
    $check($access()==='RESTRICTED','short-lived verified cache survives outage');
    $check(\EpaperSaas\Services\PlatformClient::$calls===0,'valid cache avoids upstream');
    $reset('throw',['service_access_state'=>'FULL']);
    $check($access()==='FULL','recent verified FULL cache survives outage');
    $reset('throw',['error'=>'network unavailable']);
    $check($access()==='UNAVAILABLE','invalid cached response cannot grant FULL');
    $check(\EpaperSaas\Services\PlatformClient::$calls===1,'invalid cache invokes upstream');
    if($failed)exit(1);
    echo "PASS App Platform outage integration ($cases assertions)\n";
}
