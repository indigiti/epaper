<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{RedisStore,Store,Uuid};

/** Cross-node cache invalidation, revision generations and cache warming hooks. */
final class RuntimeCache
{
    private static array $generationLocal=[];

    public static function broadcast(string $tenantGuid,array $tags): void
    {
        $tags=array_values(array_unique(array_filter(array_map('strval',$tags))));
        if(!$tags)return;
        self::apply($tenantGuid,$tags);
        try{
            Store::exec(
                'INSERT INTO ep_outbox_events(outbox_event_guid,tenant_guid,event_type,payload_json,status,attempt_count,available_at,created_at) VALUES(?, ?, "CACHE_INVALIDATE", ?, "PENDING",0,NOW(6),NOW(6))',
                [Uuid::toBinary(Uuid::v7()),Uuid::toBinary($tenantGuid),json_encode(['tenant_guid'=>$tenantGuid,'tags'=>$tags],JSON_UNESCAPED_SLASHES)]
            );
        }catch(\Throwable){}
    }

    public static function apply(string $tenantGuid,array $tags): array
    {
        $deleted=0;$bumped=[];
        foreach($tags as$tag){
            if($tag==='dashboard'){RedisStore::del('dashboard:v2:'.$tenantGuid);RedisStore::del('runtime:v2:'.$tenantGuid);RedisStore::del('runtime:v2.0.1.4:'.$tenantGuid);$deleted+=3;}
            elseif($tag==='reader_state'){$deleted+=RedisStore::deleteByPrefix('reader:state:v2:'.$tenantGuid.':');}
            elseif($tag==='settings'){$deleted+=RedisStore::deleteByPrefix('settings:v2:'.$tenantGuid.':');$bumped['settings']=self::bumpGeneration($tenantGuid,'settings');}
            elseif($tag==='entitlements'){RedisStore::del('tenant:'.$tenantGuid.':entitlements');$deleted++;}
            elseif($tag==='navigation'){$deleted+=RedisStore::deleteByPrefix('navigation:v2:active:'.$tenantGuid.':');$bumped['navigation']=self::bumpGeneration($tenantGuid,'navigation');}
            elseif(in_array($tag,['public','public-editions','editions'],true)){$bumped['public-editions']=self::bumpGeneration($tenantGuid,'public-editions');}
            elseif(in_array($tag,['master-data','publications'],true)){$bumped['master-data']=self::bumpGeneration($tenantGuid,'master-data');}
            elseif($tag==='highlights'){$bumped['highlights']=self::bumpGeneration($tenantGuid,'highlights');}
            elseif($tag==='seo'){$bumped['seo']=self::bumpGeneration($tenantGuid,'seo');}
        }
        return ['ok'=>true,'deleted'=>$deleted,'bumped'=>$bumped,'tags'=>$tags];
    }

    public static function generation(string $tenantGuid,string $namespace): int
    {
        $namespace=self::safeNamespace($namespace);$local=$tenantGuid.'|'.$namespace;if(isset(self::$generationLocal[$local]))return self::$generationLocal[$local];
        $key='generation:v3:'.$tenantGuid.':'.$namespace;$raw=RedisStore::get($key);if($raw!==null&&ctype_digit($raw))return self::$generationLocal[$local]=max(1,(int)$raw);
        $all=self::readGenerationFile($tenantGuid);$value=max(1,(int)($all[$namespace]??1));RedisStore::set($key,(string)$value,0);return self::$generationLocal[$local]=$value;
    }

    public static function bumpGeneration(string $tenantGuid,string $namespace): int
    {
        $namespace=self::safeNamespace($namespace);$current=self::generation($tenantGuid,$namespace);$next=$current+1;$key='generation:v3:'.$tenantGuid.':'.$namespace;RedisStore::set($key,(string)$next,0);$all=self::readGenerationFile($tenantGuid);$all[$namespace]=$next;self::writeGenerationFile($tenantGuid,$all);self::$generationLocal[$tenantGuid.'|'.$namespace]=$next;return$next;
    }

    public static function queueWarm(string $tenantGuid,?string $editionGuid=null,array $extra=[]): void
    {
        $payload=array_merge(['tenant_guid'=>$tenantGuid,'edition_guid'=>$editionGuid],$extra);
        try{Store::exec('INSERT INTO ep_outbox_events(outbox_event_guid,tenant_guid,event_type,payload_json,status,attempt_count,available_at,created_at) VALUES(?, ?, "CACHE_WARM", ?, "PENDING",0,DATE_ADD(NOW(6),INTERVAL 1 SECOND),NOW(6))',[Uuid::toBinary(Uuid::v7()),Uuid::toBinary($tenantGuid),json_encode($payload,JSON_UNESCAPED_SLASHES)]);}catch(\Throwable){}
    }

    public static function warmCurrentTenant(): array
    {
        $out=['public'=>[],'manifests'=>[],'seo'=>[]];try{$out['public']=PublicReadCache::warm();}catch(\Throwable$e){$out['public_error']=$e->getMessage();}try{$out['manifests']=EditionManifest::warmPublished(20);}catch(\Throwable$e){$out['manifest_error']=$e->getMessage();}try{$out['seo']=SeoDiscovery::warm();}catch(\Throwable$e){$out['seo_error']=$e->getMessage();}return$out;
    }

    private static function safeNamespace(string $namespace): string{return preg_replace('/[^a-z0-9_.-]+/i','-',strtolower(trim($namespace)))?:'default';}
    private static function generationDir(): string{$p=dirname(__DIR__,2).'/storage/cache/generations';if(!is_dir($p))@mkdir($p,0775,true);return$p;}
    private static function generationFile(string $tenantGuid): string{return self::generationDir().'/'.substr(hash('sha256',$tenantGuid),0,24).'.json';}
    private static function readGenerationFile(string $tenantGuid): array{$f=self::generationFile($tenantGuid);if(!is_file($f))return[];$d=json_decode((string)@file_get_contents($f),true);return is_array($d)?$d:[];}
    private static function writeGenerationFile(string $tenantGuid,array $data): void{$f=self::generationFile($tenantGuid);$tmp=$f.'.tmp.'.bin2hex(random_bytes(4));$json=json_encode($data,JSON_UNESCAPED_SLASHES);if($json!==false&&@file_put_contents($tmp,$json,LOCK_EX)!==false)@rename($tmp,$f);else@unlink($tmp);}
}
