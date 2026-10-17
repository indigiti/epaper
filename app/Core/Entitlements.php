<?php
namespace Digiti\Core;

final class Entitlements
{
    public static function effective(string $productTenantGuid): array
    {
        $cache=RedisStore::getJson('entitlements:'.$productTenantGuid); if($cache)return $cache;
        $rows=Store::all('SELECT f.feature_key,pf.enabled,pf.limit_value,pf.config_json FROM product_tenants pt JOIN subscriptions s ON s.product_tenant_guid=pt.product_tenant_guid AND s.status IN ("ACTIVE","PAST_DUE","GRACE_PERIOD") JOIN plans p ON p.plan_guid=s.plan_guid JOIN plan_features pf ON pf.plan_guid=p.plan_guid JOIN features f ON f.feature_guid=pf.feature_guid WHERE pt.product_tenant_guid=?',[Uuid::toBinary($productTenantGuid)]);
        $grants=Store::all('SELECT f.feature_key,g.enabled,g.limit_value,g.config_json FROM entitlement_grants g JOIN features f ON f.feature_guid=g.feature_guid WHERE g.product_tenant_guid=? AND (g.expires_at IS NULL OR g.expires_at>NOW())',[Uuid::toBinary($productTenantGuid)]);
        $out=[]; foreach($rows as$r)$out[$r['feature_key']]=['enabled'=>(bool)$r['enabled'],'limit'=>$r['limit_value']!==null?(float)$r['limit_value']:null,'config'=>json_decode($r['config_json']?:'{}',true)?:[],'source'=>'PLAN'];
        foreach($grants as$r)$out[$r['feature_key']]=['enabled'=>(bool)$r['enabled'],'limit'=>$r['limit_value']!==null?(float)$r['limit_value']:null,'config'=>json_decode($r['config_json']?:'{}',true)?:[],'source'=>'GRANT'];
        $usage=Store::all('SELECT f.feature_key,u.used_value,u.reserved_value FROM usage_meters u JOIN features f ON f.feature_guid=u.feature_guid WHERE u.product_tenant_guid=? AND u.period_key=?',[Uuid::toBinary($productTenantGuid),gmdate('Y-m')]);$usageBy=[];foreach($usage as$u)$usageBy[$u['feature_key']]=$u;foreach($out as$key=>&$e){$u=$usageBy[$key]??['used_value'=>0,'reserved_value'=>0];$e['used']=(float)$u['used_value'];$e['reserved']=(float)$u['reserved_value'];$e['remaining']=$e['limit']===null?null:max(0,(float)$e['limit']-$e['used']-$e['reserved']);}unset($e);
        RedisStore::setJson('entitlements:'.$productTenantGuid,$out,300); return $out;
    }
}
