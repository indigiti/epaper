<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,RedisStore,RequestContext};

/**
 * Bounded-staleness control-plane authorization, independent from public content caching.
 * Never invent FULL access when Platform is unavailable.
 */
final class PlatformAccess
{
    private const ALLOWED=['FULL','RESTRICTED','BILLING_ONLY','BLOCKED'];
    /** Request-local reuse; all entries expire even in long-lived PHP CLI workers. */
    private static array $memo=[];

    public static function verifiedState(array $response): string
    {
        if(($response['ok']??null)!==true)return 'BLOCKED';
        $state=(string)($response['service_access_state']??'');
        return in_array($state,self::ALLOWED,true)?$state:'BLOCKED';
    }

    public static function currentState(): string
    {
        $tenant=RequestContext::tenantGuid();
        $now=time();
        $memo=self::$memo[$tenant]??null;
        if(is_array($memo)&&($memo['until']??0)>$now)return $memo['state'];
        // New v2 key never accepts an old cached 180-second FULL grant.
        $key='access:v2:'.$tenant;
        $cached=RedisStore::getJson($key);
        if(is_array($cached)&&($cached['_format']??null)==='access-v2'){
            $validUntil=$cached['valid_until']??null;
            $state=(string)($cached['state']??'');
            if(is_int($validUntil)&&$validUntil>=time()&&in_array($state,self::ALLOWED,true)){
                self::$memo[$tenant]=['state'=>$state,'until'=>min($validUntil,time()+10)];
                return $state;
            }
        }
        try {
            $response=PlatformClient::instance()->access($tenant);
            $state=self::verifiedState($response);
            $ttl=max(1,min(30,(int)Config::get('platform.access_cache_ttl',10)));
            RedisStore::setJson($key,['_format'=>'access-v2','state'=>$state,'valid_until'=>time()+$ttl],$ttl);
            self::$memo[$tenant]=['state'=>$state,'until'=>time()+$ttl];
            return $state;
        }catch(\Throwable){
            // Redis is optional, but Platform authority is not: never re-grant on errors.
            self::$memo[$tenant]=['state'=>'UNAVAILABLE','until'=>time()+1];
            return 'UNAVAILABLE';
        }
    }
}
