<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,Http,RedisStore,Store,Uuid};

/** Authenticated control-plane webhook. This handler must run BEFORE tenant domain resolution. */
final class PlatformInvalidation
{
    public static function verify(string $raw,string $timestamp,string $signature,string $secret,int $now): array
    {
        if(strlen($secret)<32||strlen($raw)<2||strlen($raw)>8192)throw new \DomainException('INVALID_REQUEST');
        if(!ctype_digit($timestamp)||abs($now-(int)$timestamp)>300)throw new \DomainException('EXPIRED_SIGNATURE');
        if(!preg_match('/^v1=([a-f0-9]{64})$/D',$signature,$m)
            ||!hash_equals(hash_hmac('sha256',$timestamp."\n".$raw,$secret),$m[1]))
            throw new \DomainException('SIGNATURE_INVALID');
        $p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($p)||($p['kind']??null)!=='control_plane.changed'
            ||!Uuid::isValid((string)($p['event_id']??''))
            ||!Uuid::isValid((string)($p['tenant_guid']??''))
            ||!is_array($p['hostnames']??null)||count($p['hostnames'])>32)
            throw new \DomainException('PAYLOAD_INVALID');
        $hosts=[];
        foreach($p['hostnames'] as $hostname){
            if(!is_string($hostname)||!preg_match('/^(?=.{1,253}$)[a-z0-9.-]+$/D',$hostname)
                ||$hostname!==strtolower($hostname))throw new \DomainException('HOSTNAME_INVALID');
            $hosts[$hostname]=true;
        }
        return ['event_id'=>strtolower($p['event_id']),'tenant_guid'=>strtolower($p['tenant_guid']),'hostnames'=>array_keys($hosts)];
    }

    /** Applies once, transactionally deduplicated by ep_system_state PK. */
    public static function apply(array $payload,string $raw): bool
    {
        $key='platform-invalidation:'.hash('sha256',$payload['event_id']);
        return Store::tx(static function()use($payload,$raw,$key):bool{
            $digest=hash('sha256',$raw);
            $prior=Store::one('SELECT state_value FROM ep_system_state WHERE state_key=? FOR UPDATE',[$key]);
            if($prior){
                if(!hash_equals((string)$prior['state_value'],$digest))throw new \DomainException('REPLAY_CONFLICT');
                return false;
            }
            // If Redis is configured, failure must cause a retry rather than an ACK
            // that would leave stale authorization keys behind.
            if(RedisStore::enabled()){
                $redis=RedisStore::client();
                if(!$redis)throw new \RuntimeException('Redis unavailable for revocation');
                $keys=[];
                foreach($payload['hostnames'] as $host)$keys[]=RedisStore::key('domain:v2:'.$host);
                $tenant=$payload['tenant_guid'];
                foreach(['tenant-meta:v2:'.$tenant,'access:v2:'.$tenant,'tenant:'.$tenant.':entitlements','tenant-synced:'.$tenant] as $suffix)
                    $keys[]=RedisStore::key($suffix);
                if($keys)$redis->del($keys);
            }
            // A domain mapping can also be on filesystem cache if Redis was unavailable.
            foreach($payload['hostnames'] as $host){
                RedisStore::del('domain:v2:'.$host);
                DomainResolver::invalidateFileCache('domain:v2:'.$host);
            }
            $tenant=$payload['tenant_guid'];
            foreach(['tenant-meta:v2:'.$tenant] as $scope){
                RedisStore::del($scope);
                DomainResolver::invalidateFileCache($scope);
            }
            RedisStore::del('access:v2:'.$tenant);
            RedisStore::del('tenant:'.$tenant.':entitlements');
            RedisStore::del('tenant-synced:'.$tenant);
            // Never rewrite a local tenant as ACTIVE during a suspended Platform outage.
            Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES(?,?,NOW(6))',[$key,$digest]);
            return true;
        });
    }

    public static function handle(): never
    {
        Http::privateNoStore();
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST')Http::json(['ok'=>false,'code'=>'METHOD_NOT_ALLOWED'],405);
        $raw=(string)(file_get_contents('php://input')?:'');
        try {
            $payload=self::verify($raw,(string)($_SERVER['HTTP_X_DIGITI_TIMESTAMP']??''),
                (string)($_SERVER['HTTP_X_DIGITI_SIGNATURE']??''),
                (string)Config::get('platform.invalidation_secret',''),time());
            $new=self::apply($payload,$raw);
            Http::json(['ok'=>true,'event_id'=>$payload['event_id'],'duplicate'=>!$new]);
        }catch(\Throwable $e){
            $code=$e instanceof \DomainException?$e->getMessage():'DELIVERY_NOT_APPLIED';
            // Do not expose secrets or internal storage paths to the caller.
            Http::json(['ok'=>false,'code'=>$code],$code==='DELIVERY_NOT_APPLIED'?503:400);
        }
    }
}
