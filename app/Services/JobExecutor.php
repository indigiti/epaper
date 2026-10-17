<?php
namespace EpaperSaas\Services;
use EpaperSaas\Core\{RequestContext,Store,Uuid};

/** One lock/claim/complete implementation shared by every transport. */
final class JobExecutor
{
    public static function trace(array $job,string $event,string $detail=''): void
    {
        $fresh=Store::one('SELECT payload_json FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',[$job['tenant_guid'],$job['job_guid']]);
        $p=QueueService::payload($fresh?:$job);$trail=(array)($p['trace']??[]);
        $trail[]=['at'=>gmdate('c'),'event'=>$event,'detail'=>substr($detail,0,500)];$p['trace']=array_slice($trail,-16);
        Store::exec('UPDATE ep_processing_jobs SET payload_json=? WHERE tenant_guid=? AND job_guid=?',[json_encode($p,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE),$job['tenant_guid'],$job['job_guid']]);
    }

    public static function run(string $tenant,string $guid,string $worker,?string $lane=null,bool $direct=false): array
    {
        // Canonicalize UUIDs before deriving the advisory lock.
        $tb=Uuid::toBinary($tenant);$jb=Uuid::toBinary($guid);$guid=Uuid::fromBinary($jb);
        $lock=EditionOperations::jobLock($guid);
        if((int)Store::value('SELECT GET_LOCK(?,0)',[$lock])!==1)return ['outcome'=>'BUSY'];
        $original=RequestContext::all();$job=null;
        try{
            $job=Store::one('SELECT *,IF(available_at IS NULL OR available_at<=NOW(6),1,0) is_due FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',[$tb,$jb]);
            if(!$job)return ['outcome'=>'ORPHAN'];
            if($lane!==null&&QueueService::lane($job['job_type'])!==$lane)return ['outcome'=>'LANE_MISMATCH'];
            if(in_array($job['status'],['COMPLETED','FAILED','CANCELLED'],true))return ['outcome'=>'TERMINAL'];
            $t=Store::one('SELECT display_name,organisation_guid FROM ep_tenants WHERE tenant_guid=?',[$tb]);
            if(!$t)throw new \RuntimeException('Job tenant is unavailable');
            RequestContext::set(['tenant_guid'=>Uuid::fromBinary($tb),'tenant_name'=>$t['display_name'],'organisation_guid'=>!empty($t['organisation_guid'])?Uuid::fromBinary($t['organisation_guid']):null,'request_guid'=>Uuid::v7(),'correlation_guid'=>Uuid::v7()]);
            $edition=Uuid::fromBinary($job['edition_guid']);
            if(EditionOperations::stopIntent($edition)){EditionOperations::markCancelled($job);return ['outcome'=>'CANCELLED'];}
            if(!in_array($job['status'],['QUEUED','RUNNING','RETRY_WAIT'],true))throw new \RuntimeException('Unexpected processing state');
            // Owning this lock proves the previous RUNNING owner is gone, even with a recent heartbeat.
            if($job['status']!=='RUNNING'&&!(bool)$job['is_due'])return ['outcome'=>'NOT_DUE'];
            $wasAbandoned=$job['status']==='RUNNING';
            Store::exec('UPDATE ep_processing_jobs SET status="RUNNING",attempt_count=attempt_count+1,worker_id=?,locked_at=NOW(6),updated_at=NOW(6),error_message=NULL WHERE tenant_guid=? AND job_guid=?',[$worker,$tb,$jb]);
            $job=Store::one('SELECT *,IF(available_at IS NULL OR available_at<=NOW(6),1,0) is_due FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',[$tb,$jb]);
            self::trace($job,$wasAbandoned?'ABANDONED_CLAIM':'CLAIM',$worker);
            $job=Store::one('SELECT *,IF(available_at IS NULL OR available_at<=NOW(6),1,0) is_due FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',[$tb,$jb]);
            try{
                EditionOperations::assertJob($job);
                $result=$job['job_type']==='EDITION_PROCESS'?(new Processing())->processEdition($edition,$guid):(new ProcessingPipeline())->handle($job);
                if(EditionOperations::stopIntent($edition))throw new CancelledJobException('Cancellation requested');
                Store::exec('UPDATE ep_processing_jobs SET status="COMPLETED",progress=100,completed_at=NOW(6),locked_at=NULL,worker_id=NULL,error_message=NULL,updated_at=NOW(6) WHERE tenant_guid=? AND job_guid=? AND status="RUNNING"',[$tb,$jb]);
                self::trace($job,'COMPLETED',$worker);$outcome='COMPLETED';
            }catch(CancelledJobException $e){EditionOperations::markCancelled($job);self::trace($job,'CANCELLED',$e->getMessage());$outcome='CANCELLED';}
            catch(\Throwable $e){
                $fresh=Store::one('SELECT *,IF(available_at IS NULL OR available_at<=NOW(6),1,0) is_due FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',[$tb,$jb])?:$job;
                if($e instanceof PermanentJobException)QueueService::markDead($fresh,$e->getMessage());
                else QueueService::scheduleRetry($fresh,$e->getMessage(),$direct);
                self::trace($job,'ERROR',$e->getMessage());$outcome=(string)Store::value('SELECT status FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',[$tb,$jb]);
            }
            // A completion must never be rewritten as a failure merely because enqueueing finalize failed.
            if(in_array($job['job_type'],['EDITION_RENDER','PAGE_ASSETS','PAGE_OCR'],true)){
                try{QueueService::maybeFinalize($edition,(string)(QueueService::payload($job)['pipeline_guid']??$guid));}
                catch(\Throwable $e){self::trace($job,'FINALIZE_DEFERRED',$e->getMessage());}
            }
            return ['outcome'=>$outcome,'job_guid'=>$guid];
        }finally{Store::value('SELECT RELEASE_LOCK(?)',[$lock]);RequestContext::set($original);}
    }
}
