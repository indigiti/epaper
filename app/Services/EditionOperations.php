<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{RequestContext,Store,Uuid};

/** Admission and cooperative cancellation shared by web actions and workers. */
final class EditionOperations
{
    public const STALE_SECONDS = 1200;

    public static function locked(string $editionGuid, callable $action): mixed
    {
        $key='epdi_ops_'.substr(hash('sha256',RequestContext::tenantGuid().'|'.$editionGuid),0,40);
        if((int)Store::value('SELECT GET_LOCK(?,0)',[$key])!==1)throw new \RuntimeException('Another edition action is in progress. Try again shortly.');
        try{return $action();}finally{Store::value('SELECT RELEASE_LOCK(?)',[$key]);}
    }

    public static function jobLock(string $guid): string{return 'epaper_job_'.substr(hash('sha256',$guid),0,40);}
    private static function key(string $guid): string{return 'edition.stop.'.RequestContext::tenantGuid().'.'.$guid;}
    public static function stopIntent(string $guid): ?string
    {
        $v=Store::value('SELECT state_value FROM ep_system_state WHERE state_key=?',[self::key($guid)]);
        return in_array($v,['cancel','trash'],true)?$v:null;
    }
    public static function clearStop(string $guid): void{Store::exec('DELETE FROM ep_system_state WHERE state_key=?',[self::key($guid)]);}
    public static function active(string $guid): array
    {
        return Store::all('SELECT *,IF(available_at IS NULL OR available_at<=NOW(6),1,0) is_due FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid=? AND status IN ("QUEUED","RUNNING","RETRY_WAIT") ORDER BY created_at,job_guid',[TenantData::tenantBin(),Uuid::toBinary($guid)]);
    }
    public static function assertStartState(string $status): void
    {
        if(!in_array($status,['DRAFT','NEEDS_ATTENTION','READY','PROCESSING'],true))throw new \RuntimeException('Processing is unavailable for published, scheduled, archived or trashed editions.');
    }
    public static function assertJob(array $job): void
    {
        $guid=Uuid::fromBinary($job['edition_guid']);
        if(self::stopIntent($guid))throw new CancelledJobException('Cancellation requested by publisher');
        $edition=Store::one('SELECT status FROM ep_editions WHERE tenant_guid=? AND edition_guid=?',[$job['tenant_guid'],$job['edition_guid']]);
        if(!$edition||!in_array($edition['status'],['DRAFT','PROCESSING','NEEDS_ATTENTION','READY'],true))throw new CancelledJobException('Edition is protected or no longer available for processing');
        $fresh=Store::one('SELECT status FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',[$job['tenant_guid'],$job['job_guid']]);
        if(!$fresh||$fresh['status']!=='RUNNING')throw new CancelledJobException('Job no longer owns an active processing attempt');
        if($job['job_type']==='EDITION_PROCESS')return;
        $root=Store::one('SELECT job_guid FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid=? AND job_type="EDITION_RENDER" ORDER BY created_at DESC,job_guid DESC LIMIT 1',[$job['tenant_guid'],$job['edition_guid']]);
        $pipeline=(string)(QueueService::payload($job)['pipeline_guid']??Uuid::fromBinary($job['job_guid']));
        if(!$root||$pipeline!==Uuid::fromBinary($root['job_guid']))throw new CancelledJobException('Superseded processing pipeline');
    }
    public static function markCancelled(array $job): void
    {
        Store::exec('UPDATE ep_processing_jobs SET status="CANCELLED",error_message="Cancelled by publisher or superseded pipeline",completed_at=NOW(6),locked_at=NULL,worker_id=NULL,updated_at=NOW(6) WHERE tenant_guid=? AND job_guid=? AND status IN ("QUEUED","RUNNING","RETRY_WAIT")',[$job['tenant_guid'],$job['job_guid']]);
        self::settle(Uuid::fromBinary($job['edition_guid']));
    }
    public static function settle(string $guid): void
    {
        $intent=self::stopIntent($guid);if(!$intent||self::active($guid))return;
        Store::exec('UPDATE ep_editions SET status=?,publish_mode="MANUAL",scheduled_at=NULL,schedule_timezone=NULL,updated_at=NOW(6),version=version+1 WHERE tenant_guid=? AND edition_guid=? AND status NOT IN ("PUBLISHED","SCHEDULED")',[$intent==='trash'?'TRASHED':'NEEDS_ATTENTION',TenantData::tenantBin(),Uuid::toBinary($guid)]);
        self::invalidate($guid);
    }
    public static function requestStop(string $guid,string $intent): array
    {
        if(!in_array($intent,['cancel','trash'],true))throw new \InvalidArgumentException('Invalid stop action');
        return self::locked($guid,function()use($guid,$intent){
            $e=Store::one('SELECT status FROM ep_editions WHERE tenant_guid=? AND edition_guid=?',[TenantData::tenantBin(),Uuid::toBinary($guid)]);
            if(!$e)throw new \RuntimeException('Edition not found');
            if(in_array($e['status'],['PUBLISHED','SCHEDULED'],true))throw new \RuntimeException('Archive a published edition or cancel its schedule before moving it to Trash.');
            // Never downgrade an already-pending trash request to an ordinary cancellation.
            if(self::stopIntent($guid)==='trash')$intent='trash';
            Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES(?,?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',[self::key($guid),$intent]);
            Store::exec('UPDATE ep_editions SET publish_mode="MANUAL",scheduled_at=NULL,schedule_timezone=NULL,updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=?',[TenantData::tenantBin(),Uuid::toBinary($guid)]);
            foreach(self::active($guid) as$j){$lock=self::jobLock(Uuid::fromBinary($j['job_guid']));if((int)Store::value('SELECT GET_LOCK(?,0)',[$lock])!==1)continue;
                try{self::markCancelled($j);}finally{Store::value('SELECT RELEASE_LOCK(?)',[$lock]);}}
            self::settle($guid);$pending=count(self::active($guid));Audit::log('edition.'.$intent.'_requested','edition',$guid,'SUCCESS',['pending_jobs'=>$pending]);
            return ['pending'=>$pending,'intent'=>$intent];
        });
    }
    public static function recover(string $guid): array
    {
        // Recovery now requests direct consumption of existing durable jobs. No duplicate broker publishes.
        DirectRecovery::request($guid);$busy=0;foreach(self::active($guid) as$j){$key=self::jobLock(Uuid::fromBinary($j['job_guid']));if((int)Store::value('SELECT IS_FREE_LOCK(?)',[$key])!==1)$busy++;}
        return ['redispatched'=>0,'busy'=>$busy,'direct'=>true];
    }

    public static function invalidate(string $guid): void
    {
        $e=Store::one('SELECT slug FROM ep_editions WHERE tenant_guid=? AND edition_guid=?',[TenantData::tenantBin(),Uuid::toBinary($guid)]);
        if($e)EditionManifest::invalidate((string)$e['slug'],$guid);
        RuntimeCache::broadcast(RequestContext::tenantGuid(),['dashboard','public-editions','reader_state']);
    }
}
