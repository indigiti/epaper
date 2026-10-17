<?php
declare(strict_types=1);
namespace EpaperSaas\Core {
    final class Config {
        public static function get(string $key,mixed $default=null):mixed{return $default;}
    }
    final class RedisStore {
        public static function getJson(string $key):?array{return null;}
        public static function setJson(string $key,array $value,int $ttl):void{}
    }
}
namespace EpaperSaas\Services {
    final class PlatformClient {
        public static bool $unavailable=true;
        public static array $tenant=['status'=>'ACTIVE'];
        public static function instance():self{return new self();}
        public function tenant(string $guid):array{
            if(self::$unavailable)throw new \RuntimeException('Platform unreachable');
            return ['tenant'=>self::$tenant];
        }
    }
    require dirname(__DIR__).'/app/Services/DomainResolver.php';
}
namespace {
    use EpaperSaas\Services\{DomainResolver,PlatformClient};
    $tenant='00000000-0000-4000-8000-000000000099';
    $lookup=new \ReflectionMethod(DomainResolver::class,'tenantInfo');
    $failure=false;
    try{$lookup->invoke(null,$tenant,true);}catch(\RuntimeException){$failure=true;}
    if(!$failure){fwrite(STDERR,"FAIL: Platform outage could be bypassed by local tenant fallback\n");exit(1);}
    PlatformClient::$unavailable=false;
    PlatformClient::$tenant=['status'=>'SUSPENDED'];
    $failure=false;
    try{$lookup->invoke(null,$tenant,true);}catch(\RuntimeException){$failure=true;}
    if(!$failure){fwrite(STDERR,"FAIL: inactive Platform tenant not rejected\n");exit(1);}
    echo "PASS — outage and suspended Platform tenant fail closed\n";
}
