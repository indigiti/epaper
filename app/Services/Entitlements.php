<?php
namespace EpaperSaas\Services;
use EpaperSaas\Core\{RedisStore,RequestContext};
final class Entitlements
{
    public static function all(): array
    {
        $t=RequestContext::tenantGuid();$k='tenant:'.$t.':entitlements';$cached=RedisStore::getJson($k);if($cached)return$cached;try{$d=PlatformClient::instance()->entitlements($t);$e=$d['entitlements']??[];RedisStore::setJson($k,$e,300);return$e;}catch(\Throwable){return[];}
    }
    public static function has(string $key,bool $default=false): bool{$e=self::all();if(!$e)return$default;$v=$e[$key]??null;if(is_array($v))$v=$v['enabled']??$v['value']??null;return in_array($v,[true,1,'1','true','ENABLED'],true);}
    public static function limit(string $key,?float $default=null): ?float{$e=self::all();$v=$e[$key]??null;if(is_array($v))$v=$v['limit']??$v['value']??null;return is_numeric($v)?(float)$v:$default;}
}
