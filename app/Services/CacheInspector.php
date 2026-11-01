<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{RedisStore,RequestContext};

final class CacheInspector
{
    public static function snapshot(): array
    {
        $redis=RedisStore::serverMetrics();$request=RedisStore::metrics();$multi=MultiLayerCache::metrics();$fs=MultiLayerCache::filesystemStats();$manifests=EditionManifest::stats();$tenant=RequestContext::tenantGuid();$generations=[];foreach(['settings','public-editions','master-data','highlights','navigation','seo']as$g)$generations[$g]=RuntimeCache::generation($tenant,$g);
        return compact('redis','request','multi','fs','manifests','generations')+['generated_at'=>gmdate('c')];
    }
    public static function cleanup(): array
    {
        return['runtime'=>MultiLayerCache::cleanup(),'manifests'=>EditionManifest::cleanup()];
    }
    public static function purgeApplication(): array
    {
        $tenant=RequestContext::tenantGuid();$deleted=0;foreach(['public:','settings:','master:','media-asset:','tile-manifest-','search:']as$p)$deleted+=MultiLayerCache::purgeNamespace($p);foreach(['settings','public-editions','master-data','highlights','seo']as$g)RuntimeCache::bumpGeneration($tenant,$g);return['deleted'=>$deleted];
    }
    public static function warm(): array{return RuntimeCache::warmCurrentTenant();}
}
