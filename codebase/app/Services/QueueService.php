<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,RequestContext,Store,Uuid};

final class QueueService
{
    public static function driver(): string
    {
        return strtolower((string)Config::get('queue.driver', 'database'));
    }

    public static function rabbitEnabled(): bool
    {
        return self::driver() === 'rabbitmq';
    }

    public static function routingKey(string $jobType): string
    {
        return match ($jobType) {
            'EDITION_RENDER' => 'edition.render',
            'PAGE_ASSETS' => 'page.assets',
            'PAGE_OCR' => 'page.ocr',
            'EDITION_FINALIZE' => 'edition.finalize',
            default => throw new \InvalidArgumentException('Unknown processing job type: ' . $jobType),
        };
    }

    public static function lane(string $jobType): string
    {
        return match ($jobType) {
            'EDITION_RENDER' => 'render',
            'PAGE_ASSETS' => 'assets',
            'PAGE_OCR' => 'ocr',
            'EDITION_FINALIZE' => 'finalize',
            default => 'render',
        };
    }

    public static function maxAttempts(string $jobType): int
    {
        $specific = (int)Config::get('queue.max_attempts.' . strtolower($jobType), 0);
        if ($specific > 0) return max(1, min(10, $specific));
        return match ($jobType) {
            'PAGE_OCR' => 3,
            'PAGE_ASSETS' => 3,
            'EDITION_RENDER' => 3,
            'EDITION_FINALIZE' => 5,
            default => 3,
        };
    }

    /**
     * Create a durable MariaDB job and a transactional outbox event when RabbitMQ is enabled.
     */
    public static function enqueue(
        string $jobType,
        ?string $editionGuid,
        array $payload = [],
        string $priority = 'normal',
        ?string $tenantGuid = null
    ): string {
        $tenantGuid ??= RequestContext::tenantGuid();
        if($editionGuid && EditionOperations::stopIntent($editionGuid))throw new CancelledJobException('Cancellation requested');
        $jobGuid = Uuid::v7();
        if ($jobType === 'EDITION_RENDER' && empty($payload['pipeline_guid'])) $payload['pipeline_guid'] = $jobGuid;
        $payload = array_merge($payload, [
            'job_type' => $jobType,
            'routing_key' => self::routingKey($jobType),
            'priority' => $priority,
            'max_attempts' => self::maxAttempts($jobType),
            'queued_at' => gmdate('c'),
        ]);

        Store::tx(function () use ($jobGuid, $tenantGuid, $editionGuid, $jobType, $payload, $priority) {
            if($jobType==='EDITION_RENDER')Store::exec('UPDATE ep_editions SET status="PROCESSING",processing_completed_at=NULL,updated_at=NOW(6),version=version+1 WHERE tenant_guid=? AND edition_guid=?',[Uuid::toBinary($tenantGuid),Uuid::toBinary($editionGuid)]);
            Store::exec(
                'INSERT INTO ep_processing_jobs(job_guid,tenant_guid,edition_guid,job_type,status,progress,attempt_count,available_at,payload_json,created_at,updated_at) VALUES(?,?,?,? ,"QUEUED",0,0,NOW(6),?,NOW(6),NOW(6))',
                [
                    Uuid::toBinary($jobGuid),
                    Uuid::toBinary($tenantGuid),
                    $editionGuid ? Uuid::toBinary($editionGuid) : null,
                    $jobType,
                    json_encode($payload, JSON_UNESCAPED_SLASHES),
                ]
            );
            if (self::rabbitEnabled()) {
                self::insertOutbox($tenantGuid, 'QUEUE_PUBLISH', [
                    'job_guid' => $jobGuid,
                    'routing_key' => self::routingKey($jobType),
                    'exchange_kind' => 'main',
                    'priority' => $priority,
                ]);
            }
        });
        return $jobGuid;
    }

    public static function enqueueEditionRender(string $editionGuid, string $priority = 'normal', array $options = []): string
    {
        return EditionOperations::locked($editionGuid,function()use($editionGuid,$priority,$options){
            $tenant=RequestContext::tenantGuid();
            $e=Store::one('SELECT status FROM ep_editions WHERE tenant_guid=? AND edition_guid=?',[Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);
            if(!$e)throw new \RuntimeException('Edition not found');
            EditionOperations::assertStartState((string)$e['status']);
            $active=EditionOperations::active($editionGuid);
            if(EditionOperations::stopIntent($editionGuid)){
                if($active)throw new \RuntimeException('Cancellation is still pending. Wait for the active worker to stop.');
                EditionOperations::clearStop($editionGuid);
            }
            // The only allowed overlap is the current finalizer handing off bounded automatic rescue.
            $rescueParent=(string)($options['auto_rescue_parent_pipeline']??'');
            $blocking=array_filter($active,fn($j)=>!($rescueParent!==''&&$j['job_type']==='EDITION_FINALIZE'&&(QueueService::payload($j)['pipeline_guid']??'')===$rescueParent));
            if($blocking)throw new \RuntimeException('This edition has active jobs. Use Recover current jobs or Cancel processing; a second pipeline was not started.');
            $job=self::enqueue('EDITION_RENDER',$editionGuid,array_merge(['edition_guid'=>$editionGuid],$options),$priority,$tenant);
            return $job;
        });
    }

    public static function enqueuePageJob(string $jobType, string $editionGuid, string $pageGuid, int $pageNumber, string $pipelineGuid, array $extraPayload = []): string
    {
        if (!in_array($jobType, ['PAGE_ASSETS','PAGE_OCR'], true)) throw new \InvalidArgumentException('Invalid page job type');
        $tenant = RequestContext::tenantGuid();
        $existing = Store::one(
            'SELECT job_guid FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid=? AND job_type=? AND status IN ("QUEUED","RUNNING","RETRY_WAIT","COMPLETED") AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,"$.page_guid"))=? AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,"$.pipeline_guid"))=? ORDER BY created_at DESC LIMIT 1',
            [Uuid::toBinary($tenant), Uuid::toBinary($editionGuid), $jobType, $pageGuid, $pipelineGuid]
        );
        if ($existing) {
            $jobGuid = Uuid::fromBinary($existing['job_guid']);
            self::ensureDispatched($jobGuid, $tenant, 'normal', false);
            return $jobGuid;
        }
        return self::enqueue($jobType, $editionGuid, array_merge([
            'edition_guid'=>$editionGuid,
            'page_guid'=>$pageGuid,
            'page_number'=>$pageNumber,
            'pipeline_guid'=>$pipelineGuid,
        ], $extraPayload), 'normal', $tenant);
    }

    public static function enqueueFinalize(string $editionGuid, string $pipelineGuid): ?string
    {
        $lock='epdi_finalize_'.substr(hash('sha256',RequestContext::tenantGuid().'|'.$editionGuid),0,40);
        if((int)Store::value('SELECT GET_LOCK(?,0)',[$lock])!==1)return null;
        try{
        $tenant = RequestContext::tenantGuid();
        $existing = Store::one(
            'SELECT job_guid FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid=? AND job_type="EDITION_FINALIZE" AND status IN ("QUEUED","RUNNING","RETRY_WAIT","COMPLETED","FAILED","CANCELLED") AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,"$.pipeline_guid"))=? ORDER BY created_at DESC LIMIT 1',
            [Uuid::toBinary($tenant), Uuid::toBinary($editionGuid), $pipelineGuid]
        );
        if ($existing) {
            $jobGuid = Uuid::fromBinary($existing['job_guid']);
            self::ensureDispatched($jobGuid, $tenant, 'normal', false);
            return $jobGuid;
        }
        return self::enqueue('EDITION_FINALIZE', $editionGuid, ['edition_guid'=>$editionGuid,'pipeline_guid'=>$pipelineGuid], 'normal', $tenant);
        }finally{Store::value('SELECT RELEASE_LOCK(?)',[$lock]);}
    }

    /** Called by worker after a transient failure. The current RabbitMQ delivery is ACKed after this transaction commits. */
    public static function scheduleRetry(array $job, string $error, bool $direct = false): string
    {
        if(EditionOperations::stopIntent(Uuid::fromBinary($job['edition_guid']))){EditionOperations::markCancelled($job);return 'cancelled';}
        $current=Store::one('SELECT status FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',[$job['tenant_guid'],$job['job_guid']]);
        if(!$current||in_array($current['status'],['CANCELLED','COMPLETED','FAILED'],true))return 'terminal';
        $jobGuid = Uuid::fromBinary($job['job_guid']);
        $tenantGuid = Uuid::fromBinary($job['tenant_guid']);
        $payload = self::payload($job);
        $attempt = (int)$job['attempt_count'];
        $maxAttempts = (int)($payload['max_attempts'] ?? self::maxAttempts((string)$job['job_type']));
        if ($attempt >= $maxAttempts) {
            self::markDead($job, $error);
            return 'dead';
        }
        $delay = match ($attempt) {
            1 => '30s',
            2 => '5m',
            default => '30m',
        };
        $seconds = $delay === '30s' ? 30 : ($delay === '5m' ? 300 : 1800);
        Store::tx(function () use ($job, $jobGuid, $tenantGuid, $payload, $error, $delay, $seconds, $direct) {
            $payload['stage'] = 'RETRY_WAIT';
            $payload['message'] = (self::rabbitEnabled() && !$direct ? 'Retry scheduled via RabbitMQ (' : 'Retry scheduled via MariaDB (') . $delay . ')';
            $payload['last_error'] = substr($error, 0, 500);
            $payload['retry_delay'] = $delay;
            $payload['heartbeat_at'] = gmdate('c');
            if (self::rabbitEnabled() && !$direct) {
                Store::exec(
                    'UPDATE ep_processing_jobs SET status="RETRY_WAIT",available_at=DATE_ADD(NOW(6),INTERVAL ? SECOND),error_message=?,locked_at=NULL,worker_id=NULL,payload_json=?,updated_at=NOW(6) WHERE tenant_guid=? AND job_guid=?',
                    [$seconds, substr($error,0,1000), json_encode($payload, JSON_UNESCAPED_SLASHES), $job['tenant_guid'], $job['job_guid']]
                );
                self::insertOutbox($tenantGuid, 'QUEUE_PUBLISH', [
                    'job_guid'=>$jobGuid,
                    'routing_key'=>self::routingKey((string)$job['job_type']),
                    'exchange_kind'=>'retry',
                    'retry_delay'=>$delay,
                ]);
            } else {
                Store::exec(
                    'UPDATE ep_processing_jobs SET status="QUEUED",error_message=?,locked_at=NULL,worker_id=NULL,payload_json=?,available_at=DATE_ADD(NOW(6),INTERVAL ? SECOND),updated_at=NOW(6) WHERE tenant_guid=? AND job_guid=?',
                    [substr($error,0,1000), json_encode($payload, JSON_UNESCAPED_SLASHES), $seconds, $job['tenant_guid'], $job['job_guid']]
                );
            }
        });
        return $delay;
    }

    public static function markDead(array $job, string $error): void
    {
        if(EditionOperations::stopIntent(Uuid::fromBinary($job['edition_guid']))){EditionOperations::markCancelled($job);return;}
        $tenantGuid = Uuid::fromBinary($job['tenant_guid']);
        $jobGuid = Uuid::fromBinary($job['job_guid']);
        $payload = self::payload($job);
        Store::tx(function () use ($job, $tenantGuid, $jobGuid, $payload, $error) {
            $p = $payload;
            $p['stage'] = 'FAILED';
            $p['message'] = 'Maximum attempts exhausted';
            $p['last_error'] = substr($error, 0, 500);
            $p['heartbeat_at'] = gmdate('c');
            Store::exec(
                'UPDATE ep_processing_jobs SET status="FAILED",error_message=?,completed_at=NOW(6),locked_at=NULL,worker_id=NULL,payload_json=?,updated_at=NOW(6) WHERE tenant_guid=? AND job_guid=?',
                [substr($error,0,1000), json_encode($p, JSON_UNESCAPED_SLASHES), $job['tenant_guid'], $job['job_guid']]
            );
            if (self::rabbitEnabled()) {
                self::insertOutbox($tenantGuid, 'QUEUE_PUBLISH', [
                    'job_guid'=>$jobGuid,
                    'routing_key'=>self::routingKey((string)$job['job_type']),
                    'exchange_kind'=>'dead',
                ]);
            }
        });
        if(in_array((string)$job['job_type'],['EDITION_RENDER','EDITION_FINALIZE','EDITION_PROCESS'],true)){
            Store::exec('UPDATE ep_editions SET status="NEEDS_ATTENTION",updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=? AND status IN ("DRAFT","PROCESSING","READY")',[$job['tenant_guid'],$job['edition_guid']]);
        }
        if((string)$job['job_type']==='EDITION_RENDER'&&!empty($job['edition_guid'])&&ProcessingRecovery::automaticRescueEnabled()&&ProcessingRecovery::canAutoRescue($error)){
            $attempt=max(0,(int)($payload['auto_rescue_attempt']??0));$limit=ProcessingRecovery::automaticRescueLimit();
            if($attempt<$limit){
                $editionGuid=Uuid::fromBinary($job['edition_guid']);$edition=Store::one('SELECT status FROM ep_editions WHERE tenant_guid=? AND edition_guid=?',[$job['tenant_guid'],$job['edition_guid']]);
                if($edition&&!EditionOperations::active($editionGuid)&&!in_array((string)$edition['status'],['PUBLISHED','ARCHIVED','SCHEDULED','TRASHED'],true)){
                    $recommended=ProcessingRecovery::recommended($error);$next=$attempt+1;
                    $newJob=self::enqueueEditionRender($editionGuid,'high',['processing_profile'=>'auto_recovery','processing_overrides'=>$recommended['overrides'],'reprocess_reason'=>substr($error,0,500),'auto_rescue_attempt'=>$next,'auto_rescue_parent_job'=>$jobGuid]);
                    Store::exec('UPDATE ep_editions SET status="PROCESSING",processing_completed_at=NULL,updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=?',[$job['tenant_guid'],$job['edition_guid']]);
                    Store::exec('UPDATE ep_processing_jobs SET payload_json=JSON_SET(COALESCE(payload_json,JSON_OBJECT()),"$.auto_rescue_queued",true,"$.auto_rescue_job",?,"$.auto_rescue_attempt",?) WHERE tenant_guid=? AND job_guid=?',[$newJob,$next,$job['tenant_guid'],$job['job_guid']]);
                }
            }
        }
    }

    public static function payload(array $job): array
    {
        $p = json_decode((string)($job['payload_json'] ?? ''), true);
        return is_array($p) ? $p : [];
    }

    /**
     * Overall edition progress from independent stage jobs.
     * Render 10%, assets 50%, OCR 35%, finalize 5%.
     */
    public static function editionProgress(string $editionGuid): array
    {
        $tenant = RequestContext::tenantGuid();
        $root = Store::one(
            'SELECT * FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid=? AND job_type="EDITION_RENDER" ORDER BY created_at DESC LIMIT 1',
            [Uuid::toBinary($tenant), Uuid::toBinary($editionGuid)]
        );
        if (!$root) return [
            'overall'=>0.0,
            'render'=>['progress'=>0.0,'total'=>0,'queued'=>0,'running'=>0,'retry'=>0,'completed'=>0,'failed'=>0],
            'assets'=>['progress'=>0.0,'total'=>0,'queued'=>0,'running'=>0,'retry'=>0,'completed'=>0,'failed'=>0],
            'ocr'=>['progress'=>0.0,'total'=>0,'queued'=>0,'running'=>0,'retry'=>0,'completed'=>0,'failed'=>0],
            'finalize'=>['progress'=>0.0,'total'=>0,'queued'=>0,'running'=>0,'retry'=>0,'completed'=>0,'failed'=>0],
            'rows'=>[], 'pipeline_guid'=>null,
        ];
        $rootPayload = self::payload($root);
        $pipelineGuid = (string)($rootPayload['pipeline_guid'] ?? Uuid::fromBinary($root['job_guid']));
        $all = Store::all(
            'SELECT job_guid,job_type,status,progress,attempt_count,error_message,payload_json,updated_at,created_at,TIMESTAMPDIFF(SECOND,updated_at,NOW(6)) age_seconds FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid=? AND job_type IN ("EDITION_RENDER","PAGE_ASSETS","PAGE_OCR","EDITION_FINALIZE") ORDER BY created_at',
            [Uuid::toBinary($tenant), Uuid::toBinary($editionGuid)]
        );
        $rows=[];
        foreach($all as $r){
            $rp=self::payload($r);
            $rpipeline=(string)($rp['pipeline_guid'] ?? ((string)$r['job_type']==='EDITION_RENDER'?Uuid::fromBinary($r['job_guid']):''));
            if($rpipeline===$pipelineGuid)$rows[]=$r;
        }
        $by=[];foreach($rows as$r)$by[$r['job_type']][]=$r;
        $avg=function(string$type)use($by):float{$set=$by[$type]??[];if(!$set)return 0.0;return array_sum(array_map(fn($r)=>(float)$r['progress'],$set))/count($set);};
        $counts=function(string$type)use($by):array{$out=['total'=>0,'queued'=>0,'running'=>0,'retry'=>0,'completed'=>0,'failed'=>0];foreach($by[$type]??[]as$r){$out['total']++;$st=(string)$r['status'];if($st==='QUEUED')$out['queued']++;elseif($st==='RUNNING')$out['running']++;elseif($st==='RETRY_WAIT')$out['retry']++;elseif($st==='COMPLETED')$out['completed']++;elseif($st==='FAILED')$out['failed']++;}return$out;};
        $render=$avg('EDITION_RENDER');$assets=$avg('PAGE_ASSETS');$ocr=$avg('PAGE_OCR');$finalize=$avg('EDITION_FINALIZE');
        $overall=($render*.10)+($assets*.50)+($ocr*.35)+($finalize*.05);
        return[
            'overall'=>round(min(100,max(0,$overall)),2),
            'render'=>['progress'=>round($render,2)]+$counts('EDITION_RENDER'),
            'assets'=>['progress'=>round($assets,2)]+$counts('PAGE_ASSETS'),
            'ocr'=>['progress'=>round($ocr,2)]+$counts('PAGE_OCR'),
            'finalize'=>['progress'=>round($finalize,2)]+$counts('EDITION_FINALIZE'),
            'rows'=>$rows,'pipeline_guid'=>$pipelineGuid,
        ];
    }

    public static function maybeFinalize(string $editionGuid, ?string $pipelineGuid = null): void
    {
        $tenant = RequestContext::tenantGuid();
        if(EditionOperations::stopIntent($editionGuid))return;
        if (!$pipelineGuid) {
            $root=Store::one('SELECT job_guid,payload_json FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid=? AND job_type="EDITION_RENDER" ORDER BY created_at DESC LIMIT 1',[Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);
            if(!$root)return;$rp=self::payload($root);$pipelineGuid=(string)($rp['pipeline_guid']??Uuid::fromBinary($root['job_guid']));
        }
        $latest=Store::value('SELECT job_guid FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid=? AND job_type="EDITION_RENDER" ORDER BY created_at DESC,job_guid DESC LIMIT 1',[Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);
        if(!$latest||Uuid::fromBinary($latest)!==$pipelineGuid)return;
        $rootState=Store::value('SELECT status FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',[Uuid::toBinary($tenant),Uuid::toBinary($pipelineGuid)]);
        if($rootState!=='COMPLETED')return;
        $counts=Store::one('SELECT COUNT(*) found,COALESCE(SUM(status IN ("QUEUED","RUNNING","RETRY_WAIT")),0) remaining FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid=? AND job_type IN ("PAGE_ASSETS","PAGE_OCR") AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,"$.pipeline_guid"))=?',[Uuid::toBinary($tenant),Uuid::toBinary($editionGuid),$pipelineGuid]);
        if((int)$counts['found']>0&&(int)$counts['remaining']===0)self::enqueueFinalize($editionGuid,$pipelineGuid);
    }

    /**
     * Ensure a durable MariaDB job has a transport event after switching to RabbitMQ.
     * Duplicate RabbitMQ delivery is safe because workers lock/idempotently re-check job state.
     */
    /**
     * Ensure a durable MariaDB job has transport state.
     *
     * v2.0.1.2 treats PROCESSED QUEUE_PUBLISH events as successful dispatch
     * receipts. A queued job with a confirmed receipt may legitimately be
     * waiting inside RabbitMQ and must not be blindly redispatched.
     */
    public static function ensureDispatched(
        string $jobGuid,
        ?string $tenantGuid = null,
        string $priority = 'normal',
        bool $forceDueNow = false
    ): array {
        $tenantGuid ??= RequestContext::tenantGuid();
        if (!self::rabbitEnabled()) return ['ok'=>true,'created'=>false,'driver'=>'database'];
        $tenantBin=Uuid::toBinary($tenantGuid);$job=Store::one('SELECT job_guid,job_type,status,updated_at FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',[$tenantBin,Uuid::toBinary($jobGuid)]);
        if(!$job)return['ok'=>false,'created'=>false,'reason'=>'JOB_NOT_FOUND'];
        $status=(string)$job['status'];$recoveredStale=false;
        if(in_array($status,['COMPLETED','FAILED','CANCELLED'],true))return['ok'=>true,'created'=>false,'reason'=>'TERMINAL'];
        if($status==='RUNNING')return['ok'=>true,'created'=>false,'reason'=>'ACTIVE_RUNNING'];
        $event=Store::one('SELECT outbox_event_guid,status,last_error,created_at,processed_at FROM ep_outbox_events WHERE tenant_guid=? AND event_type="QUEUE_PUBLISH" AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,"$.job_guid"))=? ORDER BY created_at DESC LIMIT 1',[$tenantBin,$jobGuid]);
        if($event){
            $es=(string)$event['status'];
            if(in_array($es,['PENDING','PROCESSING'],true)){
                if($forceDueNow&&$es==='PENDING')Store::exec('UPDATE ep_outbox_events SET available_at=NOW(6),last_error=NULL WHERE tenant_guid=? AND outbox_event_guid=?',[$tenantBin,$event['outbox_event_guid']]);
                return['ok'=>true,'created'=>false,'reason'=>'ACTIVE_OUTBOX','dispatch_status'=>$es];
            }
            if($es==='PROCESSED'&&!$recoveredStale)return['ok'=>true,'created'=>false,'reason'=>'DISPATCH_CONFIRMED','dispatch_status'=>$es];
            if($es==='FAILED'&&!$recoveredStale)return['ok'=>false,'created'=>false,'reason'=>'DISPATCH_FAILED','dispatch_status'=>$es,'last_error'=>(string)($event['last_error']??'')];
            // A stale RUNNING job was previously consumed by a worker. Its old
            // PROCESSED receipt no longer proves that a live broker copy exists,
            // so one recovery dispatch is appropriate.
        }
        if(!in_array($status,['QUEUED','RETRY_WAIT'],true))return['ok'=>true,'created'=>false,'reason'=>'NO_DISPATCH_NEEDED'];
        self::insertOutbox($tenantGuid,'QUEUE_PUBLISH',['job_guid'=>$jobGuid,'routing_key'=>self::routingKey((string)$job['job_type']),'exchange_kind'=>'main','priority'=>$priority,'recovered'=>true,'recovery_reason'=>$recoveredStale?'STALE_RUNNING':'MISSING_DISPATCH_RECEIPT']);
        return['ok'=>true,'created'=>true,'reason'=>$recoveredStale?'STALE_JOB_REDISPATCHED':'OUTBOX_CREATED'];
    }

    /** Current DB-job to latest-outbox transport relationship. */
    public static function dispatchAnalysis(?string $tenantGuid=null): array
    {
        $tenantGuid??=RequestContext::tenantGuid();$tb=Uuid::toBinary($tenantGuid);
        $jobs=Store::all('SELECT job_guid,job_type,status,updated_at,available_at FROM ep_processing_jobs WHERE tenant_guid=? AND status IN ("QUEUED","RETRY_WAIT") ORDER BY created_at LIMIT 1000',[$tb]);
        $events=Store::all('SELECT status,payload_json,last_error,created_at,processed_at FROM ep_outbox_events WHERE tenant_guid=? AND event_type="QUEUE_PUBLISH" ORDER BY created_at DESC LIMIT 5000',[$tb]);
        $latest=[];foreach($events as$e){$p=json_decode((string)($e['payload_json']??''),true);$jg=is_array($p)?(string)($p['job_guid']??''):'';if($jg!==''&&!isset($latest[$jg]))$latest[$jg]=$e;}
        $out=['db_queued'=>0,'db_retry_wait'=>0,'dispatch_pending'=>0,'dispatch_processing'=>0,'dispatch_confirmed'=>0,'dispatch_failed'=>0,'missing_dispatch_receipt'=>0,'missing_job_guids'=>[],'failed_job_guids'=>[],'sample'=>[]];
        foreach($jobs as$j){$jg=Uuid::fromBinary($j['job_guid']);$st=(string)$j['status'];if($st==='QUEUED')$out['db_queued']++;else$out['db_retry_wait']++;$e=$latest[$jg]??null;$ds=$e?(string)$e['status']:'MISSING';
            if($ds==='PENDING')$out['dispatch_pending']++;elseif($ds==='PROCESSING')$out['dispatch_processing']++;elseif($ds==='PROCESSED')$out['dispatch_confirmed']++;elseif($ds==='FAILED'){$out['dispatch_failed']++;$out['failed_job_guids'][]=$jg;}else{$out['missing_dispatch_receipt']++;$out['missing_job_guids'][]=$jg;}
            if(count($out['sample'])<30)$out['sample'][]=['job_guid'=>$jg,'job_type'=>(string)$j['job_type'],'job_status'=>$st,'dispatch_status'=>$ds,'updated_at'=>$j['updated_at']??null,'last_error'=>$e?trim((string)($e['last_error']??'')):null];
        }
        $out['active_jobs']=$out['db_queued']+$out['db_retry_wait'];return$out;
    }

    /** Classify FAILED outbox rows without mutating anything. */
    public static function failedOutboxAnalysis(?string $tenantGuid=null): array
    {
        $tenantGuid??=RequestContext::tenantGuid();$tb=Uuid::toBinary($tenantGuid);
        $failed=Store::all('SELECT outbox_event_guid,event_type,payload_json,last_error,created_at FROM ep_outbox_events WHERE tenant_guid=? AND status="FAILED" ORDER BY created_at LIMIT 500',[$tb]);
        $all=Store::all('SELECT outbox_event_guid,status,payload_json,created_at FROM ep_outbox_events WHERE tenant_guid=? AND event_type="QUEUE_PUBLISH" ORDER BY created_at DESC LIMIT 5000',[$tb]);$latest=[];
        foreach($all as$e){$p=json_decode((string)($e['payload_json']??''),true);$jg=is_array($p)?(string)($p['job_guid']??''):'';if($jg!==''&&!isset($latest[$jg]))$latest[$jg]=$e;}
        $wanted=[];foreach($failed as$row){$p=json_decode((string)($row['payload_json']??''),true);$jg=is_array($p)?(string)($p['job_guid']??''):'';if($jg!==''&&Uuid::isValid($jg))$wanted[$jg]=true;}
        $jobCache=[];if($wanted){$bins=array_map(static fn(string $g)=>Uuid::toBinary($g),array_keys($wanted));$ph=implode(',',array_fill(0,count($bins),'?'));$rows=Store::all('SELECT job_guid,status FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid IN ('.$ph.')',array_merge([$tb],$bins));foreach($rows as$j)$jobCache[Uuid::fromBinary($j['job_guid'])]=$j;}
        $items=[];$counts=['total'=>count($failed),'close_probe'=>0,'close_orphan'=>0,'close_terminal'=>0,'close_superseded'=>0,'recover_confirm'=>0,'invalid_payload'=>0,'unknown_active'=>0,'closeable'=>0,'recoverable'=>0,'actionable'=>0];
        foreach($failed as$row){$type=(string)($row['event_type']??'');$p=json_decode((string)($row['payload_json']??''),true);$jg=is_array($p)?(string)($p['job_guid']??''):'';$class='unknown_active';$reason='Unknown failed event requires operator review';
            if($type==='QUEUE_PROBE'){$class='close_probe';$reason='Historical worker probe';}
            elseif($type==='QUEUE_PUBLISH'){
                if($jg===''||!Uuid::isValid($jg)){$class='invalid_payload';$reason='Invalid or missing job GUID';}
                else{
                    $job=$jobCache[$jg]??null;
                    if(!$job){$class='close_orphan';$reason='Job no longer exists';}
                    elseif(in_array((string)$job['status'],['COMPLETED','FAILED','CANCELLED'],true)){$class='close_terminal';$reason='Associated job is terminal';}
                    else{
                        $le=$latest[$jg]??null;$same=$le&&hash_equals(bin2hex((string)$row['outbox_event_guid']),bin2hex((string)$le['outbox_event_guid']));
                        if($le&&!$same&&in_array((string)$le['status'],['PENDING','PROCESSING','PROCESSED'],true)){$class='close_superseded';$reason='A later dispatch state exists';}
                        elseif(self::isConfirmFailure((string)($row['last_error']??''))){$class='recover_confirm';$reason='Known php-amqplib publisher-confirm bookkeeping failure';}
                        else{$class='unknown_active';$reason='Active job has an unclassified transport failure';}
                    }
                }
            }
            $counts[$class]++;if(str_starts_with($class,'close_'))$counts['closeable']++;elseif($class==='recover_confirm'){$counts['recoverable']++;$counts['actionable']++;}else $counts['actionable']++;
            if(count($items)<500)$items[]=['event_guid'=>Uuid::fromBinary($row['outbox_event_guid']),'event_type'=>$type,'job_guid'=>$jg?:null,'classification'=>$class,'reason'=>$reason,'error'=>trim((string)($row['last_error']??'')),'created_at'=>$row['created_at']??null];
        }
        return['counts'=>$counts,'items'=>$items];
    }

    /** Apply only classifications that are safe to close/recover. Unknown rows remain untouched. */
    public static function safeReconcile(bool $apply=false,?string $tenantGuid=null): array
    {
        $tenantGuid??=RequestContext::tenantGuid();$tb=Uuid::toBinary($tenantGuid);$analysis=self::failedOutboxAnalysis($tenantGuid);$applied=['closed'=>0,'confirm_recovered'=>0,'unchanged'=>0];
        if(!$apply)return['dry_run'=>true,'analysis'=>$analysis,'applied'=>$applied];
        foreach($analysis['items'] as$item){$class=(string)$item['classification'];$eg=(string)$item['event_guid'];if(!Uuid::isValid($eg)){continue;}
            if(str_starts_with($class,'close_')){$applied['closed']+=Store::exec('UPDATE ep_outbox_events SET status="PROCESSED",processed_at=NOW(6),locked_at=NULL,last_error=? WHERE tenant_guid=? AND outbox_event_guid=? AND status="FAILED"',['Closed by v2.0.1.2 recovery: '.$class,$tb,Uuid::toBinary($eg)]);}
            elseif($class==='recover_confirm'){$applied['confirm_recovered']+=Store::exec('UPDATE ep_outbox_events SET status="PENDING",attempt_count=0,available_at=NOW(6),locked_at=NULL,processed_at=NULL,last_error="Recovered publisher-confirm bookkeeping state from Web Recovery Center" WHERE tenant_guid=? AND outbox_event_guid=? AND status="FAILED"',[$tb,Uuid::toBinary($eg)]);}
            else $applied['unchanged']++;
        }
        return['dry_run'=>false,'analysis'=>$analysis,'applied'=>$applied,'after'=>self::failedOutboxAnalysis($tenantGuid)];
    }

    /** Create dispatch events only for active jobs with no QUEUE_PUBLISH history at all. */
    public static function repairMissingDispatches(bool $apply=false,?string $tenantGuid=null): array
    {
        $tenantGuid??=RequestContext::tenantGuid();$analysis=self::dispatchAnalysis($tenantGuid);$created=0;$skipped=0;
        if($apply)foreach($analysis['missing_job_guids'] as$jg){$r=self::ensureDispatched((string)$jg,$tenantGuid,'high',true);if(!empty($r['created']))$created++;else$skipped++;}
        return['dry_run'=>!$apply,'missing'=>(int)$analysis['missing_dispatch_receipt'],'created'=>$created,'skipped'=>$skipped,'analysis'=>$analysis];
    }

    /**
     * Backward-compatible queue repair, now hardened against duplicate broker
     * publishes. It reconciles safe FAILED rows and dispatches only jobs that
     * have no transport receipt; confirmed broker deliveries are preserved.
     */
    public static function repairQueueBacklog(?string $tenantGuid=null): array
    {
        $tenantGuid??=RequestContext::tenantGuid();$tb=Uuid::toBinary($tenantGuid);
        $stale=Store::exec('UPDATE ep_outbox_events SET status="PENDING",locked_at=NULL,available_at=NOW(6),last_error="Recovered stale outbox publisher lock" WHERE tenant_guid=? AND status="PROCESSING" AND locked_at<DATE_SUB(NOW(6),INTERVAL 2 MINUTE)',[$tb]);
        Store::exec('UPDATE ep_outbox_events SET available_at=NOW(6) WHERE tenant_guid=? AND status="PENDING" AND available_at>NOW(6)',[$tb]);
        $reconcile=self::safeReconcile(true,$tenantGuid);$missing=self::repairMissingDispatches(true,$tenantGuid);
        return['stale_outbox_recovered'=>$stale,'safe_reconcile'=>$reconcile['applied'],'missing_dispatches'=>$missing['missing'],'missing_dispatches_created'=>$missing['created'],'confirmed_dispatches_preserved'=>(int)($missing['analysis']['dispatch_confirmed']??0)];
    }

    public static function outboxBacklog(?string $tenantGuid=null): array
    {
        $tenantGuid??=RequestContext::tenantGuid();$tb=Uuid::toBinary($tenantGuid);$last=Store::one('SELECT last_error,available_at,created_at FROM ep_outbox_events WHERE tenant_guid=? AND status="PENDING" ORDER BY created_at LIMIT 1',[$tb]);$failure=self::failedOutboxAnalysis($tenantGuid);$dispatch=self::dispatchAnalysis($tenantGuid);$fc=$failure['counts'];
        return['pending'=>(int)Store::value('SELECT COUNT(*) FROM ep_outbox_events WHERE tenant_guid=? AND status="PENDING"',[$tb]),'processing'=>(int)Store::value('SELECT COUNT(*) FROM ep_outbox_events WHERE tenant_guid=? AND status="PROCESSING"',[$tb]),'failed'=>(int)$fc['total'],'failed_total'=>(int)$fc['total'],'failed_actionable'=>(int)$fc['actionable'],'failed_closeable'=>(int)$fc['closeable'],'recoverable_confirm_failed'=>(int)$fc['recoverable'],'unknown_failed'=>(int)($fc['unknown_active']+$fc['invalid_payload']),'missing_dispatch_receipt'=>(int)$dispatch['missing_dispatch_receipt'],'dispatch_confirmed'=>(int)$dispatch['dispatch_confirmed'],'oldest_pending_seconds'=>$last?max(0,time()-strtotime((string)$last['created_at'])):0,'next_available_at'=>$last['available_at']??null,'last_error'=>$last?trim((string)($last['last_error']??'')):null];
    }

    private static function isConfirmFailure(string $error): bool
    {
        return str_starts_with($error,"Server ack'ed unknown delivery_tag")||str_starts_with($error,"Server nack'ed unknown delivery_tag");
    }

    public static function operatorRedispatch(array $job): void
    {
        if(!self::rabbitEnabled())return;
        self::insertOutbox(RequestContext::tenantGuid(),'QUEUE_PUBLISH',['job_guid'=>Uuid::fromBinary($job['job_guid']),'routing_key'=>self::routingKey($job['job_type']),'exchange_kind'=>'main','priority'=>'high']);
    }

    private static function insertOutbox(?string $tenantGuid, string $eventType, array $payload): void
    {
        Store::exec(
            'INSERT INTO ep_outbox_events(outbox_event_guid,tenant_guid,event_type,payload_json,status,attempt_count,available_at,created_at) VALUES(?,?,?,? ,"PENDING",0,NOW(6),NOW(6))',
            [Uuid::toBinary(Uuid::v7()), $tenantGuid ? Uuid::toBinary($tenantGuid) : null, $eventType, json_encode($payload, JSON_UNESCAPED_SLASHES)]
        );
    }
}
