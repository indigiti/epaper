<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,RequestContext,Store,Uuid};

final class OfflineVault
{
    public const VERSION = 1;

    public static function windowDays(): int
    {
        $configured=(int)TenantData::setting('reader.offline_window_days',7);
        $ent=(int)(Entitlements::limit('EPAPER.OFFLINE_DAYS',$configured)??$configured);
        return max(1,min(30,$ent>0?$ent:$configured));
    }

    public static function editionLimit(): int
    {
        $configured=(int)TenantData::setting('reader.offline_edition_limit',7);
        $ent=(int)(Entitlements::limit('EPAPER.OFFLINE_LIMIT',$configured)??$configured);
        return max(1,min(50,$ent>0?$ent:$configured));
    }

    public static function registerDevice(array $user,array $payload): array
    {
        $device=trim((string)($payload['device_guid']??''));
        if(!Uuid::isValid($device))throw new \InvalidArgumentException('Invalid offline device identifier');
        $spki=trim((string)($payload['public_key_spki']??''));
        $der=self::b64urlDecode($spki);
        if($der===''||strlen($der)<64||strlen($der)>2048)throw new \InvalidArgumentException('Invalid offline device public key');
        $pem=self::derToPem($der,'PUBLIC KEY');
        $key=@openssl_pkey_get_public($pem);
        if($key===false)throw new \InvalidArgumentException('Unsupported offline device key');
        $detail=openssl_pkey_get_details($key);$curve=(string)($detail['ec']['curve_name']??'');if(!is_array($detail)||($detail['type']??null)!==OPENSSL_KEYTYPE_EC||!in_array(strtolower($curve),['prime256v1','secp256r1'],true))throw new \InvalidArgumentException('Offline device key must use ECDH P-256');
        $label=trim((string)($payload['label']??'Browser device'));$label=mb_substr($label!==''?$label:'Browser device',0,120);
        $uid=Uuid::toBinary((string)$user['user_guid']);$tb=TenantData::tenantBin();$existing=Store::one('SELECT status FROM ep_offline_devices WHERE tenant_guid=? AND platform_user_guid=? AND device_guid=? LIMIT 1',[$tb,$uid,Uuid::toBinary($device)]);if($existing&&strtoupper((string)$existing['status'])==='REVOKED')throw new \RuntimeException('This offline device has been revoked. Clear this site\'s offline data before registering it again.');
        Store::exec('INSERT INTO ep_offline_devices(device_guid,tenant_guid,platform_user_guid,label,public_key_spki,status,last_seen_at,created_at,updated_at) VALUES(?,?,?,?,?,"ACTIVE",NOW(6),NOW(6),NOW(6)) ON DUPLICATE KEY UPDATE label=VALUES(label),public_key_spki=VALUES(public_key_spki),status="ACTIVE",last_seen_at=NOW(6),updated_at=NOW(6)',[Uuid::toBinary($device),$tb,$uid,$label,$spki]);
        return ['device_guid'=>$device,'label'=>$label,'status'=>'ACTIVE'];
    }

    public static function createLicense(array $user,string $editionGuid,string $deviceGuid): array
    {
        if(!Uuid::isValid($editionGuid)||!Uuid::isValid($deviceGuid))throw new \InvalidArgumentException('Invalid offline request');
        if((string)TenantData::setting('reader.offline','on')==='off')throw new \RuntimeException('Offline reading is disabled');
        if(!Entitlements::has('EPAPER.OFFLINE',true))throw new \RuntimeException('Offline reading is not included in this plan');
        $tb=TenantData::tenantBin();$uid=Uuid::toBinary((string)$user['user_guid']);
        self::expireOld($uid);
        $device=Store::one('SELECT device_guid,public_key_spki,status FROM ep_offline_devices WHERE tenant_guid=? AND platform_user_guid=? AND device_guid=? LIMIT 1',[$tb,$uid,Uuid::toBinary($deviceGuid)]);
        if(!$device||strtoupper((string)$device['status'])!=='ACTIVE')throw new \RuntimeException('Offline device is not registered or has been revoked');
        $edition=Store::one('SELECT e.edition_guid,e.slug,e.title,e.edition_date,e.page_count,p.name publication_name FROM ep_editions e JOIN ep_publications p ON p.tenant_guid=e.tenant_guid AND p.publication_guid=e.publication_guid WHERE e.tenant_guid=? AND e.edition_guid=? AND e.status="PUBLISHED" LIMIT 1',[$tb,Uuid::toBinary($editionGuid)]);
        if(!$edition)throw new \RuntimeException('Published edition not found');
        $tz=new \DateTimeZone((string)Config::get('app.timezone','Asia/Kolkata'));
        $editionDate=new \DateTimeImmutable((string)$edition['edition_date'].' 00:00:00',$tz);$today=new \DateTimeImmutable('today',$tz);$days=self::windowDays();
        $oldest=$today->modify('-'.($days-1).' days');
        if($editionDate<$oldest||$editionDate>$today)throw new \RuntimeException('This edition is outside the current '.self::windowDays().'-day offline window');
        $expiresLocal=$editionDate->modify('+'.$days.' days');
        $expiresUtc=$expiresLocal->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $active=(int)Store::value('SELECT COUNT(*) FROM ep_offline_licenses WHERE tenant_guid=? AND platform_user_guid=? AND device_guid=? AND status IN ("ISSUED","READY") AND expires_at>UTC_TIMESTAMP(6) AND edition_guid<>?',[$tb,$uid,Uuid::toBinary($deviceGuid),Uuid::toBinary($editionGuid)]);
        if($active>=self::editionLimit())throw new \RuntimeException('Offline edition limit reached. Remove an older offline edition first.');

        $pages=TenantData::pagesWithAssets($editionGuid);$manifestPages=[];$bytes=0;
        foreach($pages as$p){$asset=null;foreach(['PREVIEW','HIGHRES','THUMB'] as$type){$a=self::joinedAsset($p,$type);if($a){$asset=$a;break;}}if(!$asset)continue;$manifestPages[]=['page_number'=>(int)$p['page_number'],'asset_guid'=>$asset['asset_guid'],'mime'=>(string)($asset['mime_type']??'image/webp'),'byte_size'=>(int)($asset['byte_size']??0),'storage_path'=>(string)($asset['storage_path']??'')];$bytes+=(int)($asset['byte_size']??0);}
        if(!$manifestPages)throw new \RuntimeException('No offline-capable page assets are available yet');

        $licenseGuid=Uuid::v7();$cek=random_bytes(32);$wrapped=self::wrapForDevice($cek,(string)$device['public_key_spki'],$licenseGuid);$sealed=self::sealCek($cek,$licenseGuid);
        $manifest=['version'=>self::VERSION,'license_guid'=>$licenseGuid,'edition_guid'=>$editionGuid,'slug'=>(string)$edition['slug'],'title'=>(string)($edition['title']??''),'publication_name'=>(string)($edition['publication_name']??''),'edition_date'=>(string)$edition['edition_date'],'page_count'=>count($manifestPages),'pages'=>$manifestPages,'expires_at'=>$expiresLocal->format(DATE_ATOM),'window_days'=>$days,'device_guid'=>$deviceGuid];
        $manifestJson=json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$hash=hash('sha256',(string)$manifestJson);
        Store::tx(function()use($tb,$uid,$deviceGuid,$editionGuid,$licenseGuid,$wrapped,$sealed,$expiresUtc,$manifestJson,$hash,$bytes){
            Store::exec('UPDATE ep_offline_licenses SET status="REVOKED",revoked_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE tenant_guid=? AND platform_user_guid=? AND device_guid=? AND edition_guid=? AND status IN ("ISSUED","READY")',[$tb,$uid,Uuid::toBinary($deviceGuid),Uuid::toBinary($editionGuid)]);
            Store::exec('INSERT INTO ep_offline_licenses(license_guid,tenant_guid,platform_user_guid,device_guid,edition_guid,status,wrapped_key_json,sealed_cek,manifest_json,manifest_hash,byte_size,issued_at,expires_at,created_at,updated_at) VALUES(?,?,?,?,?,"ISSUED",?,?,?,?,?,UTC_TIMESTAMP(6),?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))',[Uuid::toBinary($licenseGuid),$tb,$uid,Uuid::toBinary($deviceGuid),Uuid::toBinary($editionGuid),json_encode($wrapped,JSON_UNESCAPED_SLASHES),$sealed,$manifestJson,$hash,$bytes,$expiresUtc]);
            Store::exec('INSERT INTO ep_offline_items(offline_guid,tenant_guid,platform_user_guid,edition_guid,status,byte_size,device_guid,license_guid,expires_at,page_count,downloaded_pages,created_at,updated_at) VALUES(?,?,?,? ,"DOWNLOADING",?,?,?,?,?,0,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE status="DOWNLOADING",byte_size=VALUES(byte_size),device_guid=VALUES(device_guid),license_guid=VALUES(license_guid),expires_at=VALUES(expires_at),page_count=VALUES(page_count),downloaded_pages=0,updated_at=UTC_TIMESTAMP(6)',[Uuid::toBinary(Uuid::v7()),$tb,$uid,Uuid::toBinary($editionGuid),$bytes,Uuid::toBinary($deviceGuid),Uuid::toBinary($licenseGuid),$expiresUtc,count(json_decode((string)$manifestJson,true)['pages']??[])]);
        });
        $clientPages=[];foreach($manifestPages as$p)$clientPages[]=['page_number'=>$p['page_number'],'mime'=>$p['mime'],'byte_size'=>$p['byte_size'],'url'=>'/api/v2/offline/licenses/'.$licenseGuid.'/pages/'.$p['page_number']];
        return ['ok'=>true,'license'=>['license_guid'=>$licenseGuid,'edition_guid'=>$editionGuid,'slug'=>(string)$edition['slug'],'title'=>(string)($edition['title']??''),'publication_name'=>(string)($edition['publication_name']??''),'edition_date'=>(string)$edition['edition_date'],'expires_at'=>$expiresLocal->format(DATE_ATOM),'page_count'=>count($clientPages),'byte_size'=>$bytes,'device_guid'=>$deviceGuid,'wrapped_key'=>$wrapped,'pages'=>$clientPages]];
    }

    public static function encryptedPage(array $user,string $licenseGuid,int $pageNumber,string $deviceGuid): array
    {
        if(!Uuid::isValid($licenseGuid)||!Uuid::isValid($deviceGuid)||$pageNumber<1)throw new \InvalidArgumentException('Invalid offline page request');
        $tb=TenantData::tenantBin();$uid=Uuid::toBinary((string)$user['user_guid']);
        $row=Store::one('SELECT license_guid,device_guid,status,sealed_cek,manifest_json,expires_at FROM ep_offline_licenses WHERE tenant_guid=? AND platform_user_guid=? AND license_guid=? AND status IN ("ISSUED","READY") AND expires_at>UTC_TIMESTAMP(6) LIMIT 1',[$tb,$uid,Uuid::toBinary($licenseGuid)]);
        if(!$row)throw new \RuntimeException('Offline license has expired, was revoked, or was not found');if(!hash_equals(strtolower($deviceGuid),strtolower((string)Uuid::fromBinary($row['device_guid']))))throw new \RuntimeException('Offline license device mismatch');
        $manifest=json_decode((string)$row['manifest_json'],true);$page=null;foreach(($manifest['pages']??[])as$p)if((int)($p['page_number']??0)===$pageNumber){$page=$p;break;}if(!$page)throw new \RuntimeException('Offline page not found');
        $path=dirname(__DIR__,2).'/storage/'.ltrim((string)($page['storage_path']??''),'/');if(!is_file($path))throw new \RuntimeException('Offline page file missing');
        $plain=(string)file_get_contents($path);$cek=self::openSealedCek((string)$row['sealed_cek'],$licenseGuid);$iv=random_bytes(12);$tag='';$mime=(string)($page['mime']??'image/webp');$aad=$licenseGuid.':'.$pageNumber.':'.$mime;$cipher=openssl_encrypt($plain,'aes-256-gcm',$cek,OPENSSL_RAW_DATA,$iv,$tag,$aad,16);if($cipher===false)throw new \RuntimeException('Offline page encryption failed');
        return ['cipher'=>$cipher,'iv'=>self::b64url($iv),'tag'=>self::b64url($tag),'mime'=>$mime,'plain_bytes'=>strlen($plain),'cipher_bytes'=>strlen($cipher),'expires_at'=>(string)$row['expires_at']];
    }

    public static function complete(array $user,string $licenseGuid,string $deviceGuid,int $downloaded,int $bytes): array
    {
        $tb=TenantData::tenantBin();$uid=Uuid::toBinary((string)$user['user_guid']);$row=Store::one('SELECT edition_guid,device_guid,manifest_json,status,expires_at FROM ep_offline_licenses WHERE tenant_guid=? AND platform_user_guid=? AND license_guid=? LIMIT 1',[$tb,$uid,Uuid::toBinary($licenseGuid)]);if(!$row)throw new \RuntimeException('Offline license not found');if(!hash_equals(strtolower($deviceGuid),strtolower((string)Uuid::fromBinary($row['device_guid']))))throw new \RuntimeException('Offline license device mismatch');$m=json_decode((string)$row['manifest_json'],true);$total=count($m['pages']??[]);$ready=$total>0&&$downloaded>=$total;
        Store::exec('UPDATE ep_offline_licenses SET status=?,updated_at=UTC_TIMESTAMP(6) WHERE tenant_guid=? AND platform_user_guid=? AND license_guid=?',[$ready?'READY':'ISSUED',$tb,$uid,Uuid::toBinary($licenseGuid)]);
        Store::exec('UPDATE ep_offline_items SET status=?,downloaded_pages=?,byte_size=?,updated_at=UTC_TIMESTAMP(6) WHERE tenant_guid=? AND platform_user_guid=? AND license_guid=?',[$ready?'READY':'DOWNLOADING',max(0,$downloaded),max(0,$bytes),$tb,$uid,Uuid::toBinary($licenseGuid)]);
        return ['ok'=>true,'status'=>$ready?'READY':'DOWNLOADING','downloaded_pages'=>$downloaded,'page_count'=>$total];
    }

    public static function revokeEdition(array $user,string $editionGuid): void
    {
        $tb=TenantData::tenantBin();$uid=Uuid::toBinary((string)$user['user_guid']);Store::exec('UPDATE ep_offline_licenses SET status="REVOKED",revoked_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE tenant_guid=? AND platform_user_guid=? AND edition_guid=? AND status<>"REVOKED"',[$tb,$uid,Uuid::toBinary($editionGuid)]);
    }

    public static function revokeDevice(array $user,string $deviceGuid): void
    {
        $tb=TenantData::tenantBin();$uid=Uuid::toBinary((string)$user['user_guid']);Store::tx(function()use($tb,$uid,$deviceGuid){Store::exec('UPDATE ep_offline_devices SET status="REVOKED",revoked_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE tenant_guid=? AND platform_user_guid=? AND device_guid=?',[$tb,$uid,Uuid::toBinary($deviceGuid)]);Store::exec('UPDATE ep_offline_licenses SET status="REVOKED",revoked_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE tenant_guid=? AND platform_user_guid=? AND device_guid=? AND status<>"REVOKED"',[$tb,$uid,Uuid::toBinary($deviceGuid)]);});
    }

    public static function devices(array $user): array
    {
        $rows=Store::all('SELECT device_guid,label,status,last_seen_at,created_at,revoked_at,(SELECT COUNT(*) FROM ep_offline_licenses l WHERE l.tenant_guid=d.tenant_guid AND l.platform_user_guid=d.platform_user_guid AND l.device_guid=d.device_guid AND l.status="READY" AND l.expires_at>UTC_TIMESTAMP(6)) offline_editions FROM ep_offline_devices d WHERE tenant_guid=? AND platform_user_guid=? ORDER BY last_seen_at DESC',[TenantData::tenantBin(),Uuid::toBinary((string)$user['user_guid'])]);return TenantData::toGuidRows($rows,['device_guid']);
    }

    public static function expireOld(string $uidBin): void
    {
        $tb=TenantData::tenantBin();Store::exec('UPDATE ep_offline_licenses SET status="EXPIRED",updated_at=UTC_TIMESTAMP(6) WHERE tenant_guid=? AND platform_user_guid=? AND status IN ("ISSUED","READY") AND expires_at<=UTC_TIMESTAMP(6)',[$tb,$uidBin]);Store::exec('UPDATE ep_offline_items SET status="EXPIRED",updated_at=UTC_TIMESTAMP(6) WHERE tenant_guid=? AND platform_user_guid=? AND expires_at IS NOT NULL AND expires_at<=UTC_TIMESTAMP(6)',[$tb,$uidBin]);
    }

    private static function joinedAsset(array $page,string $type): ?array
    {
        $prefix=strtolower($type);$guid=$page[$prefix.'_asset_guid']??null;if(!$guid)return null;return ['asset_guid'=>(string)$guid,'mime_type'=>(string)($page[$prefix.'_mime_type']??'image/webp'),'byte_size'=>(int)($page[$prefix.'_byte_size']??0),'storage_path'=>(string)($page[$prefix.'_storage_path']??'')];
    }

    private static function wrapForDevice(string $cek,string $clientSpki,string $licenseGuid): array
    {
        $clientPem=self::derToPem(self::b64urlDecode($clientSpki),'PUBLIC KEY');$peer=openssl_pkey_get_public($clientPem);if($peer===false)throw new \RuntimeException('Offline device public key is invalid');$server=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);if($server===false)throw new \RuntimeException('Could not create offline wrapping key');$secret=openssl_pkey_derive($peer,$server,32);if($secret===false)throw new \RuntimeException('Could not derive offline device secret');$wrapKey=hash_hkdf('sha256',$secret,32,'epdi-offline-wrap-v1',$licenseGuid);$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($cek,'aes-256-gcm',$wrapKey,OPENSSL_RAW_DATA,$iv,$tag,$licenseGuid,16);if($cipher===false)throw new \RuntimeException('Could not wrap offline content key');$details=openssl_pkey_get_details($server);if(!is_array($details)||empty($details['key']))throw new \RuntimeException('Could not export offline wrapping key');$ephemeral=self::pemToDer((string)$details['key']);return ['algorithm'=>'ECDH-P256+HKDF-SHA256+AES-256-GCM','server_public_spki'=>self::b64url($ephemeral),'iv'=>self::b64url($iv),'tag'=>self::b64url($tag),'ciphertext'=>self::b64url($cipher),'salt'=>$licenseGuid,'info'=>'epdi-offline-wrap-v1'];
    }

    private static function sealCek(string $cek,string $licenseGuid): string
    {
        $key=hash('sha256','epdi-offline-seal-v1|'.(string)Config::get('app.key'),true);$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($cek,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,$licenseGuid,16);if($cipher===false)throw new \RuntimeException('Could not seal offline content key');return json_encode(['iv'=>self::b64url($iv),'tag'=>self::b64url($tag),'ciphertext'=>self::b64url($cipher)],JSON_UNESCAPED_SLASHES);
    }

    private static function openSealedCek(string $sealed,string $licenseGuid): string
    {
        $d=json_decode($sealed,true);if(!is_array($d))throw new \RuntimeException('Offline content key is invalid');$key=hash('sha256','epdi-offline-seal-v1|'.(string)Config::get('app.key'),true);$plain=openssl_decrypt(self::b64urlDecode((string)($d['ciphertext']??'')),'aes-256-gcm',$key,OPENSSL_RAW_DATA,self::b64urlDecode((string)($d['iv']??'')),self::b64urlDecode((string)($d['tag']??'')),$licenseGuid);if($plain===false||strlen($plain)!==32)throw new \RuntimeException('Offline content key could not be opened');return $plain;
    }

    private static function b64url(string $raw): string{return rtrim(strtr(base64_encode($raw),'+/','-_'),'=');}
    private static function b64urlDecode(string $value): string{$v=strtr($value,'-_','+/');$v.=str_repeat('=',(4-strlen($v)%4)%4);$d=base64_decode($v,true);return $d===false?'':$d;}
    private static function derToPem(string $der,string $label): string{return "-----BEGIN {$label}-----\n".chunk_split(base64_encode($der),64,"\n")."-----END {$label}-----\n";}
    private static function pemToDer(string $pem): string{$clean=preg_replace('/-----BEGIN [^-]+-----|-----END [^-]+-----|\s+/','',$pem)??'';$d=base64_decode($clean,true);if($d===false)throw new \RuntimeException('Invalid PEM public key');return $d;}
}
