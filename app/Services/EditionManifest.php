<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{RedisStore,RequestContext,Store,Uuid};

/** Immutable published-edition snapshots with Redis revision pointers + filesystem fallback. */
final class EditionManifest
{
    private static array $local=[];

    public static function bySlug(string $slug,bool $buildOnMiss=true): ?array
    {
        $slug=trim($slug);if($slug==='')return null;$tenant=RequestContext::tenantGuid();$id=hash('sha256',strtolower($slug));$local=$tenant.'|'.$id;if(isset(self::$local[$local]))return self::$local[$local];
        $pointer=self::readPointer($tenant,$id);
        if($pointer){$manifest=self::readRevision($tenant,$pointer);if($manifest)return self::$local[$local]=$manifest;}
        if(!$buildOnMiss)return null;
        return self::$local[$local]=self::buildBySlug($slug);
    }

    public static function buildBySlug(string $slug): ?array
    {
        $ed=TenantData::editionBySlug($slug);if(!$ed||($ed['status']??'')!=='PUBLISHED')return null;$ed=TenantData::guidRow($ed,['edition_guid','tenant_guid','publication_guid','city_guid','language_guid','created_by_user_guid','updated_by_user_guid']);$pages=TenantData::pagesWithAssets((string)$ed['edition_guid']);return self::write($ed,$pages);
    }

    public static function buildByGuid(string $editionGuid): ?array
    {
        $ed=Store::one('SELECT e.*,p.name publication_name,p.slug publication_slug,c.name city_name,l.name language_name FROM ep_editions e JOIN ep_publications p ON p.tenant_guid=e.tenant_guid AND p.publication_guid=e.publication_guid LEFT JOIN ep_cities c ON c.tenant_guid=e.tenant_guid AND c.city_guid=e.city_guid LEFT JOIN ep_languages l ON l.tenant_guid=e.tenant_guid AND l.language_guid=e.language_guid WHERE e.tenant_guid=? AND e.edition_guid=? AND e.status="PUBLISHED" LIMIT 1',[TenantData::tenantBin(),Uuid::toBinary($editionGuid)]);if(!$ed)return null;$ed=TenantData::guidRow($ed,['edition_guid','tenant_guid','publication_guid','city_guid','language_guid','created_by_user_guid','updated_by_user_guid']);return self::write($ed,TenantData::pagesWithAssets($editionGuid));
    }

    public static function page(array $manifest,int $number): ?array
    {
        foreach((array)($manifest['pages']??[]) as$p)if((int)($p['page_number']??0)===$number)return$p;return null;
    }

    public static function invalidate(string $slug,?string $editionGuid=null): void
    {
        $tenant=RequestContext::tenantGuid();$id=hash('sha256',strtolower($slug));RedisStore::del(self::pointerRedisKey($tenant,$id));$pf=self::pointerFile($tenant,$id);if(is_file($pf))@unlink($pf);unset(self::$local[$tenant.'|'.$id]);if($editionGuid)RedisStore::deleteByPrefix('manifest:v3:'.$tenant.':revision:'.$editionGuid.':');
    }

    public static function warmPublished(int $limit=50): array
    {
        $rows=TenantData::toGuidRows(Store::all('SELECT edition_guid,slug FROM ep_editions WHERE tenant_guid=? AND status="PUBLISHED" ORDER BY edition_date DESC,created_at DESC LIMIT '.max(1,min(200,$limit)),[TenantData::tenantBin()]),['edition_guid']);$built=0;$failed=0;foreach($rows as$r){try{if(self::buildByGuid((string)$r['edition_guid']))$built++;}catch(\Throwable){$failed++;}}return compact('built','failed');
    }

    public static function stats(): array
    {
        $root=self::tenantRoot(RequestContext::tenantGuid());$revisions=0;$pointers=0;$bytes=0;if(is_dir($root))foreach(glob($root.'/*.json')?:[] as$f){$bytes+=(int)@filesize($f);if(str_starts_with(basename($f),'edition-'))$revisions++;elseif(str_starts_with(basename($f),'slug-'))$pointers++;}return compact('revisions','pointers','bytes')+['path'=>$root];
    }

    public static function cleanup(int $keepPerEdition=3,int $maxAgeDays=30): array
    {
        $root=self::tenantRoot(RequestContext::tenantGuid());$groups=[];$removed=0;$bytes=0;$cutoff=time()-max(1,$maxAgeDays)*86400;
        if(is_dir($root))foreach(glob($root.'/edition-*.json')?:[] as$f){if(preg_match('/^edition-([0-9a-f-]{36})-r(\d+)\.json$/i',basename($f),$m))$groups[strtolower($m[1])][(int)$m[2]]=$f;}
        foreach($groups as$files){krsort($files,SORT_NUMERIC);$i=0;foreach($files as$f){$i++;if($i<=$keepPerEdition)continue;if(@filemtime($f)>=$cutoff)continue;$sz=(int)@filesize($f);if(@unlink($f)){$removed++;$bytes+=$sz;}}}
        return['removed'=>$removed,'bytes'=>$bytes];
    }

    private static function write(array $ed,array $pages): array
    {
        $tenant=RequestContext::tenantGuid();$revision=max(1,(int)($ed['version']??1));$guid=(string)$ed['edition_guid'];$slug=(string)$ed['slug'];$manifest=['schema'=>3,'revision'=>$revision,'built_at'=>gmdate('c'),'edition'=>$ed,'pages'=>$pages];$file='edition-'.$guid.'-r'.$revision.'.json';$path=self::tenantRoot($tenant).'/'.$file;self::atomicJson($path,$manifest);
        $redisManifest='manifest:v3:'.$tenant.':revision:'.$guid.':'.$revision;RedisStore::setJson($redisManifest,$manifest,RedisStore::ttlWithJitter(21600,900));
        $id=hash('sha256',strtolower($slug));$pointer=['schema'=>1,'edition_guid'=>$guid,'revision'=>$revision,'file'=>$file,'slug'=>$slug,'updated_at'=>gmdate('c')];RedisStore::setJson(self::pointerRedisKey($tenant,$id),$pointer,0);self::atomicJson(self::pointerFile($tenant,$id),$pointer);self::$local[$tenant.'|'.$id]=$manifest;return$manifest;
    }

    private static function readPointer(string $tenant,string $id): ?array
    {
        $p=RedisStore::getJson(self::pointerRedisKey($tenant,$id));if(is_array($p)&&!empty($p['file']))return$p;$f=self::pointerFile($tenant,$id);if(!is_file($f))return null;$d=json_decode((string)@file_get_contents($f),true);if(is_array($d)&&!empty($d['file'])){RedisStore::setJson(self::pointerRedisKey($tenant,$id),$d,0);return$d;}return null;
    }
    private static function readRevision(string $tenant,array $pointer): ?array
    {
        $guid=(string)($pointer['edition_guid']??'');$rev=(int)($pointer['revision']??0);if($guid!==''&&$rev>0){$d=RedisStore::getJson('manifest:v3:'.$tenant.':revision:'.$guid.':'.$rev);if(is_array($d)&&isset($d['edition'],$d['pages']))return$d;}$file=basename((string)($pointer['file']??''));if($file==='')return null;$path=self::tenantRoot($tenant).'/'.$file;if(!is_file($path))return null;$d=json_decode((string)@file_get_contents($path),true);if(is_array($d)&&isset($d['edition'],$d['pages'])){if($guid!==''&&$rev>0)RedisStore::setJson('manifest:v3:'.$tenant.':revision:'.$guid.':'.$rev,$d,RedisStore::ttlWithJitter(21600,900));return$d;}return null;
    }
    private static function pointerRedisKey(string $tenant,string $id): string{return'manifest:v3:'.$tenant.':pointer:'.$id;}
    private static function root(): string{$p=dirname(__DIR__,2).'/storage/cache/compiled/editions';if(!is_dir($p))@mkdir($p,0775,true);return$p;}
    private static function tenantRoot(string $tenant): string{$p=self::root().'/'.substr(hash('sha256',$tenant),0,24);if(!is_dir($p))@mkdir($p,0775,true);return$p;}
    private static function pointerFile(string $tenant,string $id): string{return self::tenantRoot($tenant).'/slug-'.$id.'.json';}
    private static function atomicJson(string $path,array $data): void{$tmp=$path.'.tmp.'.bin2hex(random_bytes(4));$json=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);if($json===false)throw new \RuntimeException('Manifest encode failed');if(@file_put_contents($tmp,$json,LOCK_EX)===false)throw new \RuntimeException('Manifest write failed');if(!@rename($tmp,$path)){@unlink($tmp);throw new \RuntimeException('Manifest activation failed');}}
}
