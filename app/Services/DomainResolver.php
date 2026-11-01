<?php
namespace EpaperSaas\Services;
use EpaperSaas\Core\{Config,Http,RedisStore,RequestContext,Store,Uuid};
final class DomainResolver
{
    public static function isCanonicalHost(?string $host=null): bool
    {
        $host=$host?:Http::host();$canonical=parse_url((string)Config::get('app.url'),PHP_URL_HOST);return is_string($canonical)&&$canonical!==''&&strtolower($host)===strtolower($canonical);
    }
    public static function diagnose(?string $host=null): array
    {
        $host=$host?:Http::host();$canonical=parse_url((string)Config::get('app.url'),PHP_URL_HOST)?:'';$token=(string)Config::get('platform.service_token','');$defaultTenant=(string)Config::get('platform.default_tenant_guid','');
        $out=['host'=>$host,'canonical_host'=>$canonical,'canonical_match'=>self::isCanonicalHost($host),'platform_url'=>(string)Config::get('platform.url',''),'product_key'=>(string)Config::get('platform.product_key','EPAPER'),'application_key'=>(string)Config::get('platform.application_key','city_epaper'),'service_token_configured'=>$token!=='','application_guid_configured'=>(string)Config::get('platform.application_guid','')!=='','oauth_client_id_configured'=>(string)Config::get('platform.oauth_client_id','')!=='','default_tenant_guid_configured'=>$defaultTenant!==''];
        $ph=PlatformClient::instance()->health();$out['platform_health']=['ok'=>(bool)($ph['ok']??false),'status'=>(string)($ph['status']??'UNAVAILABLE')];
        try{$d=PlatformClient::instance()->resolveDomain($host);$out['domain']=['ok'=>true,'status'=>(string)($d['status']??'UNKNOWN'),'product_key'=>(string)($d['product_key']??''),'product_tenant_guid'=>(string)($d['product_tenant_guid']??'')];$tenant=(string)($d['product_tenant_guid']??'');if($tenant){try{$ti=PlatformClient::instance()->tenant($tenant);$tr=$ti['tenant']??[];$out['tenant']=['ok'=>true,'status'=>(string)($tr['status']??'UNKNOWN'),'display_name'=>(string)($tr['display_name']??$tr['organisation_name']??'')];}catch(\Throwable$e){$out['tenant']=['ok'=>false,'message'=>$e->getMessage()];}$local=Store::one('SELECT display_name,status,primary_hostname FROM ep_tenants WHERE tenant_guid=?',[Uuid::toBinary($tenant)]);$out['local_tenant']=['ok'=>$local!==null,'status'=>$local['status']??null,'display_name'=>$local['display_name']??null];}}
        catch(\Throwable$e){$out['domain']=['ok'=>false,'message'=>$e->getMessage()];if($defaultTenant&&self::isCanonicalHost($host)){try{$ti=PlatformClient::instance()->tenant($defaultTenant);$tr=$ti['tenant']??[];$out['canonical_fallback']=['ok'=>true,'product_tenant_guid'=>$defaultTenant,'status'=>(string)($tr['status']??'UNKNOWN'),'display_name'=>(string)($tr['display_name']??$tr['organisation_name']??'')];}catch(\Throwable$te){$out['canonical_fallback']=['ok'=>false,'message'=>$te->getMessage()];}}}
        if(!$out['service_token_configured'])$out['recommended_action']='CONFIGURE_SERVICE_TOKEN';elseif(!($out['domain']['ok']??false)&&!$defaultTenant)$out['recommended_action']='REGISTER_DOMAIN_OR_SET_CANONICAL_TENANT';elseif(!($out['domain']['ok']??false)&&$defaultTenant)$out['recommended_action']='VERIFY_SERVICE_SCOPES_OR_CANONICAL_TENANT';else$out['recommended_action']='NONE';
        $out['ok']=(bool)($out['domain']['ok']??false)||(bool)($out['canonical_fallback']['ok']??false);return$out;
    }
    public static function resolve(): array
    {
        $host=Http::host();$cache=RedisStore::getJson('domain:'.$host)?:self::fileCacheGet('domain:'.$host);if($cache)return self::activate($cache,$host,true);
        $canonical=parse_url((string)Config::get('app.url'),PHP_URL_HOST);$fallback=(string)Config::get('platform.default_tenant_guid','');
        try{$d=PlatformClient::instance()->resolveDomain($host);}catch(\Throwable$e){if($fallback&&$host===$canonical){$info=self::remoteTenant($fallback);$d=['ok'=>true,'domain_guid'=>null,'organisation_guid'=>$info['organisation_guid']??(string)Config::get('platform.default_organisation_guid'),'product_guid'=>$info['product_guid']??null,'product_key'=>$info['product_key']??'EPAPER','application_guid'=>(string)Config::get('platform.application_guid'),'application_key'=>Config::get('platform.application_key','city_epaper'),'product_tenant_guid'=>$fallback,'hostname'=>$host,'status'=>'CANONICAL_FALLBACK','_tenant_info'=>$info];}else throw$e;}
        if(($d['product_key']??'')!=='EPAPER')throw new \RuntimeException('Domain is not registered for EPAPER');
        $tenant=(string)($d['product_tenant_guid']??'');if($tenant==='' )throw new \RuntimeException('Resolved domain has no product tenant');
        if(empty($d['_tenant_info'])){$info=self::tenantInfo($tenant,false);if($info)$d['_tenant_info']=$info;}
        $domainTtl=max(60,(int)Config::get('platform.domain_cache_ttl',600));RedisStore::setJson('domain:'.$host,$d,$domainTtl);self::fileCacheSet('domain:'.$host,$d,$domainTtl);
        return self::activate($d,$host,false);
    }
    private static function activate(array $d,string $host,bool $cached): array
    {
        $tenant=(string)($d['product_tenant_guid']??'');if(!$tenant)throw new \RuntimeException('Resolved domain has no product tenant');
        $info=is_array($d['_tenant_info']??null)?$d['_tenant_info']:self::tenantInfo($tenant,true);
        self::ensureTenant($tenant,$d,$info,$cached);
        $ctx=['request_guid'=>Http::requestGuid(),'correlation_guid'=>Http::correlationGuid(),'hostname'=>$host,'domain_guid'=>$d['domain_guid']??null,'organisation_guid'=>$d['organisation_guid']??($info['organisation_guid']??null),'product_guid'=>$d['product_guid']??($info['product_guid']??null),'application_guid'=>$d['application_guid']??null,'tenant_guid'=>$tenant,'tenant_name'=>$info['display_name']??$info['organisation_name']??'ePaper SaaS','domain_cached'=>$cached];RequestContext::set($ctx);return$ctx;
    }
    private static function tenantInfo(string $tenant,bool $allowLocal): array
    {
        $ttl=max(60,(int)Config::get('platform.tenant_cache_ttl',900));$cached=RedisStore::getJson('tenant-meta:'.$tenant)?:self::fileCacheGet('tenant-meta:'.$tenant);if($cached)return$cached;
        if($allowLocal){$local=self::localTenant($tenant);if($local){RedisStore::setJson('tenant-meta:'.$tenant,$local,$ttl);self::fileCacheSet('tenant-meta:'.$tenant,$local,$ttl);return$local;}}
        try{$info=self::remoteTenant($tenant);if($info){RedisStore::setJson('tenant-meta:'.$tenant,$info,$ttl);self::fileCacheSet('tenant-meta:'.$tenant,$info,$ttl);}return$info;}catch(\Throwable){return $allowLocal?(self::localTenant($tenant)?:[]):[];}
    }
    private static function remoteTenant(string $tenant): array
    {
        $ti=PlatformClient::instance()->tenant($tenant);$row=$ti['tenant']??[];return is_array($row)?$row:[];
    }
    private static function localTenant(string $tenant): array
    {
        try{$row=Store::one('SELECT organisation_guid,display_name,primary_hostname,status FROM ep_tenants WHERE tenant_guid=?',[Uuid::toBinary($tenant)]);if(!$row)return[];$org=$row['organisation_guid']??null;if(is_string($org)&&strlen($org)===16)$org=Uuid::fromBinary($org);return['organisation_guid'=>$org,'display_name'=>$row['display_name']??'Publisher','primary_hostname'=>$row['primary_hostname']??null,'status'=>$row['status']??'ACTIVE'];}catch(\Throwable){return[];}
    }
    private static function ensureTenant(string $tenant,array $d,array $info,bool $cached): void
    {
        // Domain resolution is a control-plane concern, not a per-request write path.
        // Redis remembers that this local tenant row was reconciled recently.
        if($cached&&RedisStore::get('tenant-synced:'.$tenant)==='1')return;
        $bin=Uuid::toBinary($tenant);$row=Store::one('SELECT organisation_guid,display_name,primary_hostname,status FROM ep_tenants WHERE tenant_guid=?',[$bin]);
        $orgGuid=(string)($d['organisation_guid']??($info['organisation_guid']??''));$orgBin=$orgGuid!==''&&Uuid::isValid($orgGuid)?Uuid::toBinary($orgGuid):null;
        $name=(string)($info['display_name']??$info['organisation_name']??'Publisher');$incomingHost=(string)($d['hostname']??'');
        if($row){$host=trim((string)($row['primary_hostname']??''))!==''?(string)$row['primary_hostname']:($incomingHost!==''?$incomingHost:null);$changed=!self::sameBinary($row['organisation_guid']??null,$orgBin)||(string)($row['display_name']??'')!==$name||(string)($row['primary_hostname']??'')!==(string)($host??'')||strtoupper((string)($row['status']??''))!=='ACTIVE';if($changed)Store::exec('UPDATE ep_tenants SET organisation_guid=?,display_name=?,primary_hostname=?,status="ACTIVE",updated_at=NOW(6) WHERE tenant_guid=?',[$orgBin,$name,$host,$bin]);}
        else Store::exec('INSERT INTO ep_tenants(tenant_guid,organisation_guid,display_name,primary_hostname,status,created_at,updated_at) VALUES(?,?,?,?,"ACTIVE",NOW(6),NOW(6))',[$bin,$orgBin,$name,$incomingHost!==''?$incomingHost:null]);
        RedisStore::set('tenant-synced:'.$tenant,'1',max(60,(int)Config::get('platform.local_tenant_sync_ttl',600)));
    }
    private static function fileCacheGet(string $key): array
    {
        try{$path=self::fileCachePath($key);if(!is_file($path))return[];$raw=@file_get_contents($path);$env=$raw?json_decode($raw,true):null;if(!is_array($env)||(int)($env['expires_at']??0)<time()||!is_array($env['value']??null))return[];return$env['value'];}catch(\Throwable){return[];}
    }
    private static function fileCacheSet(string $key,array $value,int $ttl): void
    {
        try{$path=self::fileCachePath($key);$dir=dirname($path);if(!is_dir($dir))@mkdir($dir,0775,true);$tmp=$path.'.tmp.'.bin2hex(random_bytes(3));$json=json_encode(['expires_at'=>time()+max(30,$ttl),'value'=>$value],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);if($json!==false&&@file_put_contents($tmp,$json,LOCK_EX)!==false)@rename($tmp,$path);else @unlink($tmp);}catch(\Throwable){}
    }
    private static function fileCachePath(string $key): string
    {
        return dirname(__DIR__,2).'/storage/cache/control-plane/'.hash('sha256',$key).'.json';
    }
    private static function sameBinary(mixed $a,mixed $b): bool
    {
        if($a===null&&$b===null)return true;if(!is_string($a)||!is_string($b))return false;return hash_equals($a,$b);
    }
}
