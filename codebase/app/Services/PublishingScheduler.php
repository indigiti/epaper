<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{RequestContext,Store,Uuid};

/** Shared one-shot publishing scheduler used by cron and the Web Recovery Center. */
final class PublishingScheduler
{
    /** Publish just this edition when its previously selected publishing intent is due. */
    public static function publishEditionIfDue(string $editionGuid): bool
    {
        return EditionOperations::locked($editionGuid,function()use($editionGuid){
            if(EditionOperations::stopIntent($editionGuid)||EditionOperations::active($editionGuid))return false;
            $tenant=RequestContext::tenantGuid();
            $changed=Store::exec('UPDATE ep_editions SET status="PUBLISHED",publish_mode="MANUAL",scheduled_at=NULL,schedule_timezone=NULL,published_at=NOW(6),updated_at=NOW(6),version=version+1 WHERE tenant_guid=? AND edition_guid=? AND ((publish_mode="SCHEDULED" AND status IN ("READY","SCHEDULED") AND scheduled_at IS NOT NULL AND scheduled_at<=NOW(6)) OR (publish_mode="AUTO_READY" AND status="READY"))',[Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);
            if($changed){RuntimeCache::broadcast($tenant,['dashboard','reader_state','public-editions']);RuntimeCache::queueWarm($tenant,$editionGuid);}
            return $changed>0;
        });
    }

    public static function runOnce(string $source='cron'): array
    {
        $source=in_array($source,['cron','web'],true)?$source:'unknown';$original=RequestContext::all();
        $lock=(int)Store::value('SELECT GET_LOCK("epdi_scheduler_v2",0)');
        if($lock!==1)return['ok'=>false,'locked'=>true,'source'=>$source,'message'=>'Scheduler is already running'];
        try{
            Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES("scheduler.started_at",?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',[gmdate('c')]);
            $publishedNow=0;$staleRecovered=0;$now=date('Y-m-d H:i:s');
            $due=Store::all('SELECT edition_guid,tenant_guid FROM ep_editions WHERE ((publish_mode="SCHEDULED" AND status IN ("READY","SCHEDULED") AND scheduled_at IS NOT NULL AND scheduled_at<=?) OR (publish_mode="AUTO_READY" AND status="READY")) AND NOT EXISTS (SELECT 1 FROM ep_processing_jobs j WHERE j.tenant_guid=ep_editions.tenant_guid AND j.edition_guid=ep_editions.edition_guid AND j.status IN ("QUEUED","RUNNING","RETRY_WAIT")) ORDER BY scheduled_at IS NULL,scheduled_at LIMIT 100',[$now]);
            foreach($due as$r){
                $tenantGuid=Uuid::fromBinary($r['tenant_guid']);$editionGuid=Uuid::fromBinary($r['edition_guid']);
                RequestContext::set(['tenant_guid'=>$tenantGuid]);
                try{if(self::publishEditionIfDue($editionGuid))$publishedNow++;}catch(\RuntimeException){/* Reconsider a busy edition on the next pass. */}
            }
            if(QueueService::rabbitEnabled()){
                Store::exec('UPDATE ep_processing_jobs SET status="FAILED",error_message="Legacy processor expired after RabbitMQ migration",completed_at=NOW(6),locked_at=NULL,worker_id=NULL,updated_at=NOW(6) WHERE job_type="EDITION_PROCESS" AND status="RUNNING" AND updated_at<DATE_SUB(NOW(6),INTERVAL 30 MINUTE)');
                $stale=Store::all('SELECT * FROM ep_processing_jobs WHERE job_type IN ("EDITION_RENDER","PAGE_ASSETS","PAGE_OCR","EDITION_FINALIZE") AND status="RUNNING" AND updated_at<DATE_SUB(NOW(6),INTERVAL 20 MINUTE) ORDER BY updated_at LIMIT 100');
                foreach($stale as$job){$tenantGuid=Uuid::fromBinary($job['tenant_guid']);$tenant=Store::one('SELECT display_name,organisation_guid FROM ep_tenants WHERE tenant_guid=?',[$job['tenant_guid']]);if(!$tenant)continue;RequestContext::set(['tenant_guid'=>$tenantGuid,'tenant_name'=>$tenant['display_name']??'Publisher','organisation_guid'=>!empty($tenant['organisation_guid'])?Uuid::fromBinary($tenant['organisation_guid']):null,'request_guid'=>Uuid::v7(),'correlation_guid'=>Uuid::v7()]); $jobLock=EditionOperations::jobLock(Uuid::fromBinary($job['job_guid']));if((int)Store::value('SELECT GET_LOCK(?,0)',[$jobLock])!==1)continue;try{QueueService::scheduleRetry($job,'Recovered abandoned worker after heartbeat expired');$staleRecovered++;}finally{Store::value('SELECT RELEASE_LOCK(?)',[$jobLock]);}}
            }else{
                $stale=Store::all('SELECT * FROM ep_processing_jobs WHERE status="RUNNING" AND updated_at<DATE_SUB(NOW(6),INTERVAL 20 MINUTE) LIMIT 100');
                foreach($stale as$job){$jobLock=EditionOperations::jobLock(Uuid::fromBinary($job['job_guid']));if((int)Store::value('SELECT GET_LOCK(?,0)',[$jobLock])!==1)continue;
                    try{RequestContext::set(['tenant_guid'=>Uuid::fromBinary($job['tenant_guid'])]);if(EditionOperations::stopIntent(Uuid::fromBinary($job['edition_guid'])))EditionOperations::markCancelled($job);else Store::exec('UPDATE ep_processing_jobs SET status="QUEUED",locked_at=NULL,worker_id=NULL,available_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND job_guid=? AND status="RUNNING"',[$job['tenant_guid'],$job['job_guid']]);$staleRecovered++;}finally{Store::value('SELECT RELEASE_LOCK(?)',[$jobLock]);}}

            }
            $unfinished=Store::all('SELECT tenant_guid,edition_guid FROM ep_editions WHERE status="PROCESSING" ORDER BY updated_at LIMIT 100');
            foreach($unfinished as$u){RequestContext::set(['tenant_guid'=>Uuid::fromBinary($u['tenant_guid'])]);try{QueueService::maybeFinalize(Uuid::fromBinary($u['edition_guid']));}catch(\Throwable){}}
            $metrics=['queued_processing'=>(int)Store::value('SELECT COUNT(*) FROM ep_processing_jobs WHERE status="QUEUED"'),'running_processing'=>(int)Store::value('SELECT COUNT(*) FROM ep_processing_jobs WHERE status="RUNNING"'),'retry_wait_processing'=>(int)Store::value('SELECT COUNT(*) FROM ep_processing_jobs WHERE status="RETRY_WAIT"'),'failed_processing'=>(int)Store::value('SELECT COUNT(*) FROM ep_processing_jobs WHERE status="FAILED"'),'outbox_pending'=>(int)Store::value('SELECT COUNT(*) FROM ep_outbox_events WHERE status="PENDING"'),'outbox_failed'=>(int)Store::value('SELECT COUNT(*) FROM ep_outbox_events WHERE status="FAILED"'),'published_editions'=>(int)Store::value('SELECT COUNT(*) FROM ep_editions WHERE status="PUBLISHED"'),'queue_driver'=>QueueService::driver()];
            try{PlatformClient::instance()->heartbeat($metrics);}catch(\Throwable){}
            $entry=['at'=>gmdate('c'),'source'=>$source,'published_now'=>$publishedNow,'stale_recovered'=>$staleRecovered,'metrics'=>$metrics];$json=json_encode($entry,JSON_UNESCAPED_SLASHES);
            Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES("scheduler.last_run",?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',[$json]);
            if($source==='cron')Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES("scheduler.last_cron",?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',[$json]);
            self::appendHistory($entry);
            return['ok'=>true,'locked'=>false,'source'=>$source,'published_now'=>$publishedNow,'stale_recovered'=>$staleRecovered,'metrics'=>$metrics,'at'=>$entry['at']];
        }finally{
            try{Store::value('SELECT RELEASE_LOCK("epdi_scheduler_v2")');}catch(\Throwable){}
            if($original)RequestContext::set($original);
        }
    }

    public static function status(): array
    {
        $row=static fn(string$key)=>Store::one('SELECT state_value,updated_at,TIMESTAMPDIFF(SECOND,updated_at,NOW(6)) age_seconds FROM ep_system_state WHERE state_key=?',[$key]);$last=$row('scheduler.last_run');$cron=$row('scheduler.last_cron');$hist=Store::one('SELECT state_value FROM ep_system_state WHERE state_key="scheduler.history"');$history=$hist?json_decode((string)$hist['state_value'],true):[];if(!is_array($history))$history=[];
        return['last_run'=>$last?['age_seconds'=>max(0,(int)$last['age_seconds']),'updated_at'=>$last['updated_at'],'value'=>json_decode((string)$last['state_value'],true)]:null,'last_cron'=>$cron?['age_seconds'=>max(0,(int)$cron['age_seconds']),'updated_at'=>$cron['updated_at'],'value'=>json_decode((string)$cron['state_value'],true)]:null,'history'=>$history];
    }

    private static function appendHistory(array $entry): void
    {
        $row=Store::one('SELECT state_value FROM ep_system_state WHERE state_key="scheduler.history"');$history=$row?json_decode((string)$row['state_value'],true):[];if(!is_array($history))$history=[];$history[]=['at'=>$entry['at'],'source'=>$entry['source'],'published_now'=>$entry['published_now'],'stale_recovered'=>$entry['stale_recovered']];$history=array_slice($history,-10);
        Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES("scheduler.history",?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',[json_encode($history,JSON_UNESCAPED_SLASHES)]);
    }
}
