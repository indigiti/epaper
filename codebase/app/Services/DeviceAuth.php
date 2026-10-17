<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Auth,Config,RateLimiter,RequestContext,Store,Uuid};

final class DeviceAuth
{
    public static function enabled(): bool
    {
        $switch=strtolower((string)TenantData::setting('reader_auth.device_fallback','on'));
        return $switch==='on' && MobileAuth::channels(true)===[];
    }

    public static function ttlDays(): int
    {
        $days=(int)TenantData::setting('reader_auth.device_ttl_days',30);
        return max(1,min(365,$days));
    }

    public static function maxCreatesPerIpDay(): int
    {
        $limit=(int)TenantData::setting('reader_auth.device_limit_per_ip_day',5);
        return max(1,min(100,$limit));
    }

    public static function authenticate(string $installationId): array
    {
        if(!self::enabled()) throw new \RuntimeException('Temporary device access is not enabled.');
        self::validateInstallationId($installationId);
        $tenant=RequestContext::tenantGuid();$tb=Uuid::toBinary($tenant);$hash=self::installationHash($installationId);
        $ttl=self::ttlDays();$expiresAt=date('Y-m-d H:i:s',time()+$ttl*86400);$ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
        $ipHash=hash_hmac('sha256',$ip,(string)Config::get('app.key','epdi-device-auth'));

        $result=Store::tx(function() use($tb,$hash,$ttl,$expiresAt,$ipHash,$tenant){
            $row=Store::one('SELECT account_guid,user_guid,status,expires_at FROM ep_local_device_accounts WHERE tenant_guid=? AND installation_hash=? LIMIT 1 FOR UPDATE',[$tb,$hash]);
            if($row){
                $status=strtoupper((string)$row['status']);
                if(in_array($status,['BLOCKED','MERGED','DISABLED'],true))throw new \RuntimeException('This temporary reader account is disabled.');
                $subscriber=Store::one('SELECT status FROM ep_subscribers WHERE tenant_guid=? AND platform_user_guid=? LIMIT 1',[$tb,$row['user_guid']]);
                if($subscriber&&strtoupper((string)$subscriber['status'])!=='ACTIVE')throw new \RuntimeException('This temporary reader account is disabled.');
                // Rolling inactivity expiry: an active installation stays usable while abandoned
                // temporary identities naturally age out and are pruned by the scheduler.
                Store::exec('UPDATE ep_local_device_accounts SET status="ACTIVE",last_seen_at=NOW(6),expires_at=?,updated_at=NOW(6) WHERE tenant_guid=? AND installation_hash=?',[$expiresAt,$tb,$hash]);
                $userGuid=Uuid::fromBinary($row['user_guid']);
                return ['user'=>['user_guid'=>$userGuid,'email'=>null,'display_name'=>self::displayName($hash),'status'=>'ACTIVE'],'expires_at'=>$expiresAt,'created'=>false];
            }

            $bucket='device-auth-create:'.$tenant.':'.$ipHash;
            if(!RateLimiter::allow($bucket,self::maxCreatesPerIpDay(),86400))throw new \RuntimeException('Too many temporary device accounts were created from this network. Please try again later.');
            $recent=(int)Store::value('SELECT COUNT(*) FROM ep_local_device_accounts WHERE tenant_guid=? AND request_ip_hash=? AND created_at>=DATE_SUB(NOW(6),INTERVAL 1 DAY)',[$tb,$ipHash]);
            if($recent>=self::maxCreatesPerIpDay())throw new \RuntimeException('Too many temporary device accounts were created from this network. Please try again later.');

            $accountGuid=Uuid::v7();$userGuid=Uuid::v7();$ub=Uuid::toBinary($userGuid);
            Store::exec('INSERT INTO ep_local_device_accounts(account_guid,tenant_guid,user_guid,installation_hash,request_ip_hash,status,last_seen_at,expires_at,created_at,updated_at) VALUES(?,?,?,?,?,"ACTIVE",NOW(6),?,NOW(6),NOW(6))',[Uuid::toBinary($accountGuid),$tb,$ub,$hash,$ipHash,$expiresAt]);
            Store::exec('INSERT INTO ep_identities(platform_user_guid,email,display_name,identity_source,status,last_seen_at,created_at,updated_at) VALUES(?,NULL,?,"LOCAL_DEVICE","ACTIVE",NOW(6),NOW(6),NOW(6))',[$ub,self::displayName($hash)]);
            Store::exec('INSERT INTO ep_subscribers(subscriber_guid,tenant_guid,platform_user_guid,status,created_at,updated_at) VALUES(?,?,?,"ACTIVE",NOW(6),NOW(6))',[Uuid::toBinary(Uuid::v7()),$tb,$ub]);
            return ['user'=>['user_guid'=>$userGuid,'email'=>null,'display_name'=>self::displayName($hash),'status'=>'ACTIVE'],'expires_at'=>$expiresAt,'created'=>true];
        });
        Auth::establishLocalDevice($result['user'],$hash);
        return $result;
    }

    public static function mergeIntoMobile(string $installationId,string $mobileUserGuid): bool
    {
        if(strtolower((string)TenantData::setting('reader_auth.merge_on_verify','on'))!=='on')return false;
        if(trim($installationId)==='')return false;
        self::validateInstallationId($installationId);
        $tb=TenantData::tenantBin();$hash=self::installationHash($installationId);$target=Uuid::toBinary($mobileUserGuid);
        return Store::tx(function()use($tb,$hash,$target){
            $row=Store::one('SELECT account_guid,user_guid,status FROM ep_local_device_accounts WHERE tenant_guid=? AND installation_hash=? LIMIT 1 FOR UPDATE',[$tb,$hash]);
            if(!$row||in_array(strtoupper((string)$row['status']),['MERGED','BLOCKED','DISABLED'],true))return false;
            $source=$row['user_guid'];if(hash_equals($source,$target))return false;

            // Preserve user-owned Reader OS data. De-duplicate records with unique keys.
            Store::exec('INSERT IGNORE INTO ep_bookmarks(bookmark_guid,tenant_guid,platform_user_guid,bookmark_type,target_guid,note,created_at) SELECT bookmark_guid,tenant_guid,?,bookmark_type,target_guid,note,created_at FROM ep_bookmarks WHERE tenant_guid=? AND platform_user_guid=?',[$target,$tb,$source]);
            Store::exec('DELETE FROM ep_bookmarks WHERE tenant_guid=? AND platform_user_guid=?',[$tb,$source]);
            Store::exec('UPDATE ep_saved_articles SET platform_user_guid=? WHERE tenant_guid=? AND platform_user_guid=?',[$target,$tb,$source]);
            Store::exec('UPDATE ep_clips SET platform_user_guid=? WHERE tenant_guid=? AND platform_user_guid=?',[$target,$tb,$source]);
            Store::exec('INSERT INTO ep_history(history_guid,tenant_guid,platform_user_guid,edition_guid,page_guid,page_number,duration_seconds,last_viewed_at,created_at) SELECT history_guid,tenant_guid,?,edition_guid,page_guid,page_number,duration_seconds,last_viewed_at,created_at FROM ep_history WHERE tenant_guid=? AND platform_user_guid=? ON DUPLICATE KEY UPDATE page_guid=IF(VALUES(last_viewed_at)>=last_viewed_at,VALUES(page_guid),page_guid),page_number=IF(VALUES(last_viewed_at)>=last_viewed_at,VALUES(page_number),page_number),duration_seconds=GREATEST(duration_seconds,VALUES(duration_seconds)),last_viewed_at=GREATEST(last_viewed_at,VALUES(last_viewed_at))',[$target,$tb,$source]);
            Store::exec('DELETE FROM ep_history WHERE tenant_guid=? AND platform_user_guid=?',[$tb,$source]);
            Store::exec('INSERT IGNORE INTO ep_reader_preferences(preference_guid,tenant_guid,platform_user_guid,preferences_json,updated_at,created_at) SELECT preference_guid,tenant_guid,?,preferences_json,updated_at,created_at FROM ep_reader_preferences WHERE tenant_guid=? AND platform_user_guid=?',[$target,$tb,$source]);
            Store::exec('DELETE FROM ep_reader_preferences WHERE tenant_guid=? AND platform_user_guid=?',[$tb,$source]);
            Store::exec('UPDATE ep_notifications SET platform_user_guid=? WHERE tenant_guid=? AND platform_user_guid=?',[$target,$tb,$source]);
            Store::exec('UPDATE ep_analytics_events SET platform_user_guid=? WHERE tenant_guid=? AND platform_user_guid=?',[$target,$tb,$source]);
            Store::exec('UPDATE ep_offline_devices SET platform_user_guid=? WHERE tenant_guid=? AND platform_user_guid=?',[$target,$tb,$source]);
            Store::exec('UPDATE ep_offline_licenses SET platform_user_guid=? WHERE tenant_guid=? AND platform_user_guid=?',[$target,$tb,$source]);
            // Offline editions may collide with an already downloaded mobile-account copy.
            $items=Store::all('SELECT offline_guid,device_guid,edition_guid FROM ep_offline_items WHERE tenant_guid=? AND platform_user_guid=?',[$tb,$source]);
            foreach($items as$item){
                $dupe=Store::one('SELECT offline_guid FROM ep_offline_items WHERE tenant_guid=? AND platform_user_guid=? AND device_guid <=> ? AND edition_guid=? LIMIT 1',[$tb,$target,$item['device_guid'],$item['edition_guid']]);
                if($dupe)Store::exec('DELETE FROM ep_offline_items WHERE tenant_guid=? AND offline_guid=?',[$tb,$item['offline_guid']]);
                else Store::exec('UPDATE ep_offline_items SET platform_user_guid=? WHERE tenant_guid=? AND offline_guid=?',[$target,$tb,$item['offline_guid']]);
            }
            Store::exec('UPDATE ep_local_device_accounts SET status="MERGED",merged_to_user_guid=?,updated_at=NOW(6) WHERE tenant_guid=? AND installation_hash=?',[$target,$tb,$hash]);
            Store::exec('UPDATE ep_subscribers SET status="MERGED",updated_at=NOW(6) WHERE tenant_guid=? AND platform_user_guid=?',[$tb,$source]);
            Store::exec('UPDATE ep_identities SET status="MERGED",updated_at=NOW(6) WHERE platform_user_guid=?',[$source]);
            return true;
        });
    }

    /** @return array{expired:int,deleted:int} */
    public static function prune(int $retentionDays=30): array
    {
        $retentionDays=max(1,min(365,$retentionDays));
        $expired=Store::exec('UPDATE ep_local_device_accounts SET status="EXPIRED",updated_at=NOW(6) WHERE status="ACTIVE" AND expires_at<NOW(6)');
        $deleted=Store::exec('DELETE FROM ep_local_device_accounts WHERE status="MERGED" AND updated_at<DATE_SUB(NOW(6),INTERVAL '.$retentionDays.' DAY)');
        return ['expired'=>(int)$expired,'deleted'=>(int)$deleted];
    }

    public static function installationHash(string $installationId): string
    {
        self::validateInstallationId($installationId);
        $pepper=(string)Config::get('auth.mobile.device_pepper',Config::get('app.key','epdi-device-auth'));
        return hash_hmac('sha256',RequestContext::tenantGuid().'|'.trim($installationId),$pepper);
    }

    private static function validateInstallationId(string $installationId): void
    {
        $id=trim($installationId);
        if(strlen($id)<16||strlen($id)>128||!preg_match('/^[A-Za-z0-9._:-]+$/',$id))throw new \InvalidArgumentException('Invalid device installation identifier.');
    }

    private static function displayName(string $hash): string
    {
        return 'Temporary reader •'.strtoupper(substr($hash,-6));
    }
}
