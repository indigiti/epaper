<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,RequestContext,Store,Uuid};

final class ReaderSession
{
    public const MOBILE='MOBILE_APP';
    public const DESKTOP='DESKTOP_WEB';

    public static function inferKind(): string
    {
        $ua=(string)($_SERVER['HTTP_USER_AGENT']??'');
        return str_contains($ua,'EPDI-Android/')?self::MOBILE:self::DESKTOP;
    }

    public static function bind(string $userGuid,string $kind,?string $deviceGuid=null): string
    {
        $kind=strtoupper($kind);
        if(!in_array($kind,[self::MOBILE,self::DESKTOP],true))$kind=self::DESKTOP;
        $tenant=RequestContext::tenantGuid();$tb=Uuid::toBinary($tenant);$ub=Uuid::toBinary($userGuid);
        $guid=Uuid::v7();$gb=Uuid::toBinary($guid);$hash=self::phpSessionHash();$expires=self::expiry($kind);
        $deviceBin=$deviceGuid&&Uuid::isValid($deviceGuid)?Uuid::toBinary($deviceGuid):null;
        Store::tx(function()use($tb,$ub,$kind,$gb,$hash,$expires,$deviceBin){
            Store::exec('UPDATE ep_reader_sessions SET status="REPLACED",revoked_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND user_guid=? AND session_kind=? AND status="ACTIVE"',[$tb,$ub,$kind]);
            Store::exec('INSERT INTO ep_reader_sessions(reader_session_guid,tenant_guid,user_guid,session_kind,device_guid,session_hash,status,last_seen_at,expires_at,created_at,updated_at) VALUES(?,?,?,?,?,? ,"ACTIVE",NOW(6),?,NOW(6),NOW(6))',[$gb,$tb,$ub,$kind,$deviceBin,$hash,$expires]);
        });
        $_SESSION['reader_session_guid']=$guid;$_SESSION['reader_session_kind']=$kind;
        return $guid;
    }

    public static function attachDevice(string $deviceGuid): void
    {
        $guid=(string)($_SESSION['reader_session_guid']??'');
        if(!Uuid::isValid($guid)||!Uuid::isValid($deviceGuid))return;
        Store::exec('UPDATE ep_reader_sessions SET device_guid=?,updated_at=NOW(6) WHERE tenant_guid=? AND reader_session_guid=? AND status="ACTIVE"',[Uuid::toBinary($deviceGuid),Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($guid)]);
    }

    public static function validateOrBind(string $userGuid): bool
    {
        $current=(string)($_SESSION['reader_session_guid']??'');
        if(!Uuid::isValid($current)){self::bind($userGuid,self::inferKind());return true;}
        $row=Store::one('SELECT session_kind,session_hash,status,expires_at FROM ep_reader_sessions WHERE tenant_guid=? AND user_guid=? AND reader_session_guid=? LIMIT 1',[Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($userGuid),Uuid::toBinary($current)]);
        $active=$row&&strtoupper((string)$row['status'])==='ACTIVE'&&strtotime((string)$row['expires_at'])>=time()&&hash_equals((string)$row['session_hash'],self::phpSessionHash());
        if(!$active)return false;
        Store::exec('UPDATE ep_reader_sessions SET last_seen_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND reader_session_guid=? AND status="ACTIVE" AND last_seen_at<DATE_SUB(NOW(6),INTERVAL 5 MINUTE)',[Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($current)]);
        return true;
    }

    public static function revokeCurrent(string $reason='LOGOUT'): void
    {
        $guid=(string)($_SESSION['reader_session_guid']??'');if(!Uuid::isValid($guid))return;
        try{Store::exec('UPDATE ep_reader_sessions SET status=?,revoked_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND reader_session_guid=? AND status="ACTIVE"',[strtoupper($reason)==='LOGOUT'?'LOGGED_OUT':'REVOKED',Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($guid)]);}catch(\Throwable){}
    }

    public static function revokeKind(string $userGuid,string $kind,?string $exceptGuid=null): int
    {
        $sql='UPDATE ep_reader_sessions SET status="REPLACED",revoked_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND user_guid=? AND session_kind=? AND status="ACTIVE"';
        $params=[Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($userGuid),strtoupper($kind)];
        if($exceptGuid&&Uuid::isValid($exceptGuid)){$sql.=' AND reader_session_guid<>?';$params[]=Uuid::toBinary($exceptGuid);}
        return (int)Store::exec($sql,$params);
    }

    public static function prune(int $retentionDays=30): array
    {
        $retentionDays=max(1,min(365,$retentionDays));
        $expired=(int)Store::exec('UPDATE ep_reader_sessions SET status="EXPIRED",updated_at=NOW(6) WHERE status="ACTIVE" AND expires_at<NOW(6)');
        $deleted=(int)Store::exec('DELETE FROM ep_reader_sessions WHERE status<>"ACTIVE" AND updated_at<DATE_SUB(NOW(6),INTERVAL '.$retentionDays.' DAY)');
        return ['expired'=>$expired,'deleted'=>$deleted];
    }

    private static function expiry(string $kind): string
    {
        $key=$kind===self::MOBILE?'reader_auth.mobile_session_days':'reader_auth.desktop_session_days';
        $default=$kind===self::MOBILE?365:30;$days=(int)TenantData::setting($key,$default);$days=max(1,min(365,$days));
        return date('Y-m-d H:i:s',time()+($days*86400));
    }

    private static function phpSessionHash(): string
    {
        $id=session_id();$secret=(string)Config::get('app.key','epdi-session');return hash_hmac('sha256',$id,$secret);
    }
}
