<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Auth,Config,RateLimiter,RequestContext,Store,Uuid};

final class DesktopAuth
{
    public const TTL_DEFAULT=180;

    public static function enabled(): bool{return strtolower((string)TenantData::setting('reader_auth.desktop_login','on'))==='on';}
    public static function appApprovalEnabled(): bool{return self::enabled()&&strtolower((string)TenantData::setting('reader_auth.desktop_app_approval','on'))==='on';}
    public static function ttlSeconds(): int{$m=(int)TenantData::setting('reader_auth.desktop_challenge_minutes',3);return max(1,min(10,$m))*60;}
    public static function fallbackChannels(): array
    {
        $available=MobileAuth::channels(false);$out=[];
        if(in_array('whatsapp',$available,true)&&strtolower((string)TenantData::setting('reader_auth.desktop_whatsapp_fallback','on'))==='on')$out[]='whatsapp';
        if(in_array('sms',$available,true)&&strtolower((string)TenantData::setting('reader_auth.desktop_sms_fallback','on'))==='on')$out[]='sms';
        return$out;
    }

    public static function start(string $rawPhone): array
    {
        if(!self::enabled())throw new \RuntimeException('Desktop reader login is disabled.');
        $phone=MobileAuth::normalizePhone($rawPhone);$ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$tenant=RequestContext::tenantGuid();
        if(!RateLimiter::allow('desktop-login:'.$tenant.':'.hash('sha256',$ip),15,900))throw new \RuntimeException('Too many desktop login requests. Please try again later.');
        $_SESSION['desktop_login_phone']=$phone;
        $tb=Uuid::toBinary($tenant);$account=Store::one('SELECT a.user_guid,a.status,s.status subscriber_status,i.status identity_status FROM ep_local_mobile_accounts a LEFT JOIN ep_subscribers s ON s.tenant_guid=a.tenant_guid AND s.platform_user_guid=a.user_guid LEFT JOIN ep_identities i ON i.platform_user_guid=a.user_guid WHERE a.tenant_guid=? AND a.phone_e164=? LIMIT 1',[$tb,$phone]);
        $fallback=self::fallbackChannels();
        $eligible=$account&&strtoupper((string)$account['status'])==='ACTIVE'&&strtoupper((string)($account['subscriber_status']??''))==='ACTIVE'&&strtoupper((string)($account['identity_status']??''))==='ACTIVE';
        if(!$eligible||!self::appApprovalEnabled())return ['status'=>'FALLBACK_AVAILABLE','fallback_channels'=>$fallback,'phone_masked'=>MobileAuth::maskPhone($phone)];
        $device=TrustedAppAuth::activeForUserBinary($account['user_guid']);
        if(!$device)return ['status'=>'FALLBACK_AVAILABLE','fallback_channels'=>$fallback,'phone_masked'=>MobileAuth::maskPhone($phone)];
        $challenge=Uuid::v7();$code=(string)random_int(100000,999999);$ttl=self::ttlSeconds();$expires=date('Y-m-d H:i:s',time()+$ttl);$browserHash=self::browserSessionHash();$ipHash=hash_hmac('sha256',$ip,(string)Config::get('app.key','epdi-desktop-login'));$summary=self::clientSummary();
        Store::exec('INSERT INTO ep_desktop_login_challenges(challenge_guid,tenant_guid,user_guid,phone_e164,trusted_device_guid,display_code,browser_session_hash,requested_ip_hash,user_agent_summary,status,expires_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,"PENDING",?,NOW(6),NOW(6))',[Uuid::toBinary($challenge),$tb,$account['user_guid'],$phone,$device['device_guid'],$code,$browserHash,$ipHash,$summary,$expires]);
        $_SESSION['desktop_auth_challenges'][$challenge]=['expires'=>time()+$ttl];
        $push=FcmPush::sendDesktopChallenge((string)($device['push_token_ciphertext']??''),['challenge_guid'=>$challenge,'display_code'=>$code,'client_summary'=>$summary,'expires_at'=>$expires]);
        return ['status'=>'WAITING_FOR_APP','challenge_guid'=>$challenge,'display_code'=>$code,'expires_in'=>$ttl,'push_status'=>$push,'fallback_channels'=>$fallback,'phone_masked'=>MobileAuth::maskPhone($phone),'client_summary'=>$summary];
    }

    public static function browserStatus(string $challengeGuid): array
    {
        if(!Uuid::isValid($challengeGuid)||!isset($_SESSION['desktop_auth_challenges'][$challengeGuid]))return ['status'=>'INVALID'];
        $row=Store::one('SELECT challenge_guid,user_guid,phone_e164,status,display_code,browser_session_hash,expires_at FROM ep_desktop_login_challenges WHERE tenant_guid=? AND challenge_guid=? LIMIT 1',[Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($challengeGuid)]);
        if(!$row||!hash_equals((string)$row['browser_session_hash'],self::browserSessionHash()))return ['status'=>'INVALID'];
        $status=strtoupper((string)$row['status']);
        if($status==='PENDING'&&strtotime((string)$row['expires_at'])<time()){$status='EXPIRED';Store::exec('UPDATE ep_desktop_login_challenges SET status="EXPIRED",updated_at=NOW(6) WHERE tenant_guid=? AND challenge_guid=?',[Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($challengeGuid)]);}
        if($status==='APPROVED'){
            $userGuid=Uuid::fromBinary($row['user_guid']);$phone=(string)$row['phone_e164'];
            $user=['user_guid'=>$userGuid,'email'=>null,'display_name'=>self::displayName($phone),'status'=>'ACTIVE'];
            Auth::establishLocalMobile($user,$phone,ReaderSession::DESKTOP);
            Store::exec('UPDATE ep_desktop_login_challenges SET status="CONSUMED",consumed_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND challenge_guid=?',[Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($challengeGuid)]);
            unset($_SESSION['desktop_auth_challenges'][$challengeGuid]);return ['status'=>'AUTHENTICATED','redirect'=>'/'];
        }
        if(in_array($status,['DENIED','EXPIRED','CANCELLED','CONSUMED'],true))unset($_SESSION['desktop_auth_challenges'][$challengeGuid]);
        return ['status'=>$status,'display_code'=>(string)$row['display_code'],'expires_at'=>(string)$row['expires_at']];
    }

    public static function pendingForDevice(string $deviceGuid,string $credential): ?array
    {
        $device=TrustedAppAuth::verifiedDevice($deviceGuid,$credential);
        $row=Store::one('SELECT challenge_guid,display_code,user_agent_summary,expires_at,created_at FROM ep_desktop_login_challenges WHERE tenant_guid=? AND trusted_device_guid=? AND status="PENDING" AND expires_at>=NOW(6) ORDER BY created_at DESC LIMIT 1',[Uuid::toBinary(RequestContext::tenantGuid()),$device['device_guid']]);
        if(!$row)return null;return ['challenge_guid'=>Uuid::fromBinary($row['challenge_guid']),'display_code'=>(string)$row['display_code'],'client_summary'=>(string)$row['user_agent_summary'],'expires_at'=>(string)$row['expires_at']];
    }

    public static function decision(string $deviceGuid,string $credential,string $challengeGuid,string $decision): array
    {
        $device=TrustedAppAuth::verifiedDevice($deviceGuid,$credential);if(!Uuid::isValid($challengeGuid))throw new \InvalidArgumentException('Invalid desktop login request.');
        $decision=strtoupper($decision);if(!in_array($decision,['APPROVE','DENY'],true))throw new \InvalidArgumentException('Invalid login decision.');
        $tb=Uuid::toBinary(RequestContext::tenantGuid());$cb=Uuid::toBinary($challengeGuid);
        return Store::tx(function()use($device,$decision,$tb,$cb){
            $row=Store::one('SELECT status,expires_at,trusted_device_guid FROM ep_desktop_login_challenges WHERE tenant_guid=? AND challenge_guid=? LIMIT 1 FOR UPDATE',[$tb,$cb]);
            if(!$row||!hash_equals($row['trusted_device_guid'],$device['device_guid']))throw new \RuntimeException('Desktop login request is not available for this app.');
            $current=strtoupper((string)$row['status']);if($current!=='PENDING')return ['status'=>$current];
            if(strtotime((string)$row['expires_at'])<time()){Store::exec('UPDATE ep_desktop_login_challenges SET status="EXPIRED",updated_at=NOW(6) WHERE tenant_guid=? AND challenge_guid=? AND status="PENDING"',[$tb,$cb]);return ['status'=>'EXPIRED'];}
            $status=$decision==='APPROVE'?'APPROVED':'DENIED';
            Store::exec('UPDATE ep_desktop_login_challenges SET status=?,decided_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND challenge_guid=? AND status="PENDING"',[$status,$tb,$cb]);
            return ['status'=>$status];
        });
    }

    public static function prune(int $days=14): array
    {
        $days=max(1,min(90,$days));$expired=(int)Store::exec('UPDATE ep_desktop_login_challenges SET status="EXPIRED",updated_at=NOW(6) WHERE status="PENDING" AND expires_at<NOW(6)');$deleted=(int)Store::exec('DELETE FROM ep_desktop_login_challenges WHERE status<>"PENDING" AND updated_at<DATE_SUB(NOW(6),INTERVAL '.$days.' DAY)');return['expired'=>$expired,'deleted'=>$deleted];
    }

    private static function browserSessionHash(): string{return hash_hmac('sha256',session_id(),(string)Config::get('app.key','epdi-desktop-login'));}
    private static function clientSummary(): string
    {
        $ua=(string)($_SERVER['HTTP_USER_AGENT']??'Unknown browser');$os=str_contains($ua,'Windows')?'Windows':(str_contains($ua,'Macintosh')?'macOS':(str_contains($ua,'Linux')?'Linux':(str_contains($ua,'Android')?'Android':'Device')));
        $browser=str_contains($ua,'Edg/')?'Edge':(str_contains($ua,'Chrome/')?'Chrome':(str_contains($ua,'Firefox/')?'Firefox':(str_contains($ua,'Safari/')?'Safari':'Browser')));return substr($browser.' on '.$os,0,190);
    }
    private static function displayName(string $phone): string{$digits=preg_replace('/\D+/','',$phone)??'';return'Reader ••••'.substr($digits,-4);}
}
