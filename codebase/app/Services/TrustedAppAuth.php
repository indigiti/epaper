<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Auth,Config,RequestContext,Store,Uuid};

final class TrustedAppAuth
{
    public static function registerCurrent(string $installationId,string $credential,string $pushToken='',string $label='Android device',string $platform='ANDROID'): array
    {
        $identity=Auth::identity();$user=$identity['user']??null;$ua=(string)($_SERVER['HTTP_USER_AGENT']??'');
        if(!$user||strtoupper((string)($identity['auth_source']??''))!=='LOCAL_MOBILE'||!Auth::sessionAccessActive())throw new \RuntimeException('Verified mobile sign-in is required before trusting this app.');
        if(!str_contains($ua,'EPDI-Android/'))throw new \RuntimeException('Trusted-device registration is available only from the EP-DI Android app.');
        self::validateCredential($credential);DeviceAuth::installationHash($installationId);
        $tenant=RequestContext::tenantGuid();$tb=Uuid::toBinary($tenant);$ub=Uuid::toBinary((string)$user['user_guid']);$installHash=DeviceAuth::installationHash($installationId);$credHash=self::credentialHash($credential);$pushCipher=FcmPush::encryptToken($pushToken);
        $device=Store::tx(function()use($tb,$ub,$installHash,$credHash,$pushCipher,$label,$platform){
            $existing=Store::one('SELECT device_guid,user_guid FROM ep_reader_devices WHERE tenant_guid=? AND installation_hash=? LIMIT 1 FOR UPDATE',[$tb,$installHash]);
            Store::exec('UPDATE ep_reader_devices SET status="REPLACED",updated_at=NOW(6) WHERE tenant_guid=? AND user_guid=? AND device_type="MOBILE_APP" AND status="ACTIVE"'.($existing?' AND device_guid<>?':''),$existing?[$tb,$ub,$existing['device_guid']]:[$tb,$ub]);
            if($existing){
                if(!hash_equals($existing['user_guid'],$ub))Store::exec('UPDATE ep_reader_devices SET status="REPLACED",updated_at=NOW(6) WHERE tenant_guid=? AND device_guid=?',[$tb,$existing['device_guid']]);
                Store::exec('UPDATE ep_reader_devices SET user_guid=?,credential_hash=?,device_type="MOBILE_APP",device_label=?,platform=?,push_token_ciphertext=CASE WHEN ?<>"" THEN ? ELSE push_token_ciphertext END,status="ACTIVE",trusted_at=NOW(6),last_seen_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND device_guid=?',[$ub,$credHash,substr($label,0,120),substr($platform,0,40),$pushCipher,$pushCipher,$tb,$existing['device_guid']]);
                return Uuid::fromBinary($existing['device_guid']);
            }
            $guid=Uuid::v7();Store::exec('INSERT INTO ep_reader_devices(device_guid,tenant_guid,user_guid,installation_hash,credential_hash,device_type,device_label,platform,push_token_ciphertext,push_provider,status,trusted_at,last_seen_at,created_at,updated_at) VALUES(?,?,?,?,?,"MOBILE_APP",?,?,?,"FCM","ACTIVE",NOW(6),NOW(6),NOW(6),NOW(6))',[Uuid::toBinary($guid),$tb,$ub,$installHash,$credHash,substr($label,0,120),substr($platform,0,40),$pushCipher]);return$guid;
        });
        ReaderSession::attachDevice($device);
        return ['device_guid'=>$device,'trusted'=>true,'push_configured'=>FcmPush::configured()];
    }

    public static function updatePushToken(string $deviceGuid,string $credential,string $pushToken): bool
    {
        $row=self::verifiedDevice($deviceGuid,$credential);$cipher=FcmPush::encryptToken($pushToken);if($cipher==='')return false;
        Store::exec('UPDATE ep_reader_devices SET push_token_ciphertext=?,last_seen_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND device_guid=?',[$cipher,Uuid::toBinary(RequestContext::tenantGuid()),$row['device_guid']]);return true;
    }

    public static function verifiedDevice(string $deviceGuid,string $credential): array
    {
        if(!Uuid::isValid($deviceGuid))throw new \InvalidArgumentException('Invalid trusted device.');self::validateCredential($credential);
        $row=Store::one('SELECT device_guid,user_guid,credential_hash,status,push_token_ciphertext,device_label,platform FROM ep_reader_devices WHERE tenant_guid=? AND device_guid=? AND device_type="MOBILE_APP" LIMIT 1',[Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($deviceGuid)]);
        if(!$row||strtoupper((string)$row['status'])!=='ACTIVE'||!hash_equals((string)$row['credential_hash'],self::credentialHash($credential)))throw new \RuntimeException('Trusted app credential is invalid or revoked.');
        Store::exec('UPDATE ep_reader_devices SET last_seen_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND device_guid=?',[Uuid::toBinary(RequestContext::tenantGuid()),$row['device_guid']]);return$row;
    }

    public static function activeForUserBinary(string $userBin): ?array
    {
        return Store::one('SELECT device_guid,user_guid,push_token_ciphertext,device_label,platform,last_seen_at FROM ep_reader_devices WHERE tenant_guid=? AND user_guid=? AND device_type="MOBILE_APP" AND status="ACTIVE" ORDER BY trusted_at DESC LIMIT 1',[Uuid::toBinary(RequestContext::tenantGuid()),$userBin]);
    }

    public static function revokeForUser(string $userGuid): int
    {
        return (int)Store::exec('UPDATE ep_reader_devices SET status="REVOKED",updated_at=NOW(6) WHERE tenant_guid=? AND user_guid=? AND status="ACTIVE"',[Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($userGuid)]);
    }

    public static function prune(int $days=90): int
    {
        $days=max(7,min(365,$days));return(int)Store::exec('DELETE FROM ep_reader_devices WHERE status<>"ACTIVE" AND updated_at<DATE_SUB(NOW(6),INTERVAL '.$days.' DAY)');
    }

    private static function validateCredential(string $credential): void
    {
        if(strlen($credential)<32||strlen($credential)>200||!preg_match('/^[A-Za-z0-9_-]+$/',$credential))throw new \InvalidArgumentException('Invalid trusted-device credential.');
    }
    private static function credentialHash(string $credential): string{return hash_hmac('sha256',$credential,(string)Config::get('auth.desktop.device_pepper',Config::get('app.key','epdi-trusted-device')));}
}
