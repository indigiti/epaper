<?php
namespace EpaperSaas\Services;
use EpaperSaas\Core\{RedisStore,RequestContext};

final class Entitlements
{
    public static function all(): array
    {
        // Entitlements are never an independent authorization grant.
        if(PlatformAccess::currentState()!=='FULL')return [];
        $tenant=RequestContext::tenantGuid();
        $key='tenant:'.$tenant.':entitlements';
        $cached=RedisStore::getJson($key);
        if(is_array($cached)){
            if(($cached['_format']??null)==='entitlements-v1'&&is_array($cached['data']??null))
                return $cached['data'];
            if($cached)return $cached; // read prior unwrapped caches until expiry
        }
        try {
            $response=PlatformClient::instance()->entitlements($tenant);
            $entitlements=$response['entitlements']??[];
            if(!is_array($entitlements))return [];
            RedisStore::setJson($key,['_format'=>'entitlements-v1','data'=>$entitlements],300);
            return $entitlements;
        }catch(\Throwable){return [];}
    }
    public static function has(string $key,bool $default=false): bool
    {
        $e=self::all();
        if(!$e)return $default;
        $v=$e[$key]??null;
        if(is_array($v))$v=$v['enabled']??$v['value']??null;
        return in_array($v,[true,1,'1','true','ENABLED'],true);
    }
    public static function limit(string $key,?float $default=null): ?float
    {
        $e=self::all();
        $v=$e[$key]??null;
        if(is_array($v))$v=$v['limit']??$v['value']??null;
        return is_numeric($v)?(float)$v:$default;
    }
}
