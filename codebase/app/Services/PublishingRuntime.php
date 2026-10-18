<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,RedisStore,RequestContext};
use EpaperSaas\Contracts\PublishingMetricsRepository;
use EpaperSaas\Repositories\SqlPublishingMetricsRepository;

/** Short-lived operational snapshot for dashboards and health surfaces. */
final class PublishingRuntime
{
    public static function snapshot(bool $refresh=false, ?PublishingMetricsRepository $repository=null): array
    {
        $tenant=RequestContext::tenantGuid();$key='runtime:v2.0.1.4:'.$tenant;
        if(!$refresh){$cached=RedisStore::getJson($key);if(is_array($cached))return$cached;}
        $tb=TenantData::tenantBin();
        $row=($repository ?? new SqlPublishingMetricsRepository())->summary($tb);
        $outbox=QueueService::failedOutboxAnalysis();$row['outbox_failed']=(int)($outbox['counts']['actionable']??0);
        $workers=InfrastructureConfig::workerStatus();
        $queues=[];try{$queues=QueueService::rabbitEnabled()?RabbitMq::instance()->queueStats():[];}catch(\Throwable){}
        $attention=[];
        $schema=SchemaDoctor::quickStatus();
        if(!($schema['ok']??false))$attention[]=['code'=>'SCHEMA','severity'=>'danger','title'=>'Database upgrade required','detail'=>'One or more EP-DI v2 schema changes are missing. Open Upgrade Center to repair them safely.','href'=>'/admin/system/upgrade'];
        if(!($workers['ok']??false))$attention[]=['code'=>'WORKERS','severity'=>'warning','title'=>'Background workers','detail'=>'One or more Supervisor lanes are below the configured worker count.','href'=>'/admin/system/infrastructure/supervisor'];
        if((int)($row['outbox_failed']??0)>0)$attention[]=['code'=>'OUTBOX','severity'=>'danger','title'=>'Queue dispatch failures','detail'=>(int)$row['outbox_failed'].' outbox events need review.','href'=>'/admin/processing'];
        if((int)($row['stuck_jobs']??0)>0)$attention[]=['code'=>'STUCK_JOBS','severity'=>'danger','title'=>'Processing without progress','detail'=>(int)$row['stuck_jobs'].' queued/running job(s) have not updated for more than 20 minutes.','href'=>'/admin/processing'];
        $schedulerAge=array_key_exists('scheduler_age_seconds',$row)&&$row['scheduler_age_seconds']!==null?(int)$row['scheduler_age_seconds']:null;
        if($schedulerAge===null||$schedulerAge>150)$attention[]=['code'=>'SCHEDULER','severity'=>$schedulerAge===null?'warning':'danger','title'=>'Publishing scheduler','detail'=>$schedulerAge===null?'No scheduler heartbeat has been recorded.':'Scheduler heartbeat is '.(int)$schedulerAge.' seconds old.','href'=>'/admin/system/health'];
        if((int)($row['failed_jobs']??0)>0)$attention[]=['code'=>'JOBS','severity'=>'danger','title'=>'Failed processing jobs','detail'=>(int)$row['failed_jobs'].' editions need processing recovery.','href'=>'/admin/processing'];
        if((int)($row['recent_errors']??0)>0)$attention[]=['code'=>'ERRORS','severity'=>'warning','title'=>'Open error incidents','detail'=>(int)$row['recent_errors'].' error events were recorded in the last 7 days.','href'=>'/admin/errors'];
        $snapshot=[
            'published_editions'=>(int)($row['published_editions']??0),'pages'=>(int)($row['pages']??0),'processing'=>(int)($row['processing']??0),'failed_jobs'=>(int)($row['failed_jobs']??0),'stuck_jobs'=>(int)($row['stuck_jobs']??0),
            'outbox_pending'=>(int)($row['outbox_pending']??0),'outbox_failed'=>(int)($row['outbox_failed']??0),'recent_errors'=>(int)($row['recent_errors']??0),'scheduler_age_seconds'=>$schedulerAge,
            'workers'=>['ok'=>(bool)($workers['ok']??false),'source'=>$workers['source']??'unknown','expected'=>$workers['expected']??[],'live'=>$workers['live']??[]],
            'queues'=>$queues,'attention'=>$attention,'status'=>$attention?'ATTENTION':'HEALTHY','generated_at'=>gmdate('c'),
            'media_offload'=>trim((string)Config::get('media.x_accel_prefix',''))!==''?'x-accel':'php',
        ];
        RedisStore::setJson($key,$snapshot,RedisStore::ttlWithJitter(8,2));
        return$snapshot;
    }
}
