<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,RedisStore,RequestContext,Store,Uuid};

/** Read-mostly operational analysis and narrowly-scoped repair helpers. */
final class RecoveryCenter
{
    public static function snapshot(): array
    {
        $tenant=RequestContext::tenantGuid();$tb=Uuid::toBinary($tenant);$dispatch=QueueService::dispatchAnalysis($tenant);$failures=QueueService::failedOutboxAnalysis($tenant);$rabbit=RabbitMq::instance()->ping();$queues=QueueService::rabbitEnabled()?RabbitMq::instance()->queueStats():[];$workers=SupervisorRuntime::diagnosis();$scheduler=PublishingScheduler::status();$redis=self::redisTelemetry(false);
        $processing=Store::one('SELECT
            SUM(status="QUEUED") queued,
            SUM(status="RUNNING") running,
            SUM(status="RETRY_WAIT") retry_wait,
            SUM(status="FAILED") failed,
            SUM(status="RUNNING" AND updated_at<DATE_SUB(NOW(6),INTERVAL 20 MINUTE)) stale_running,
            TIMESTAMPDIFF(SECOND,MIN(CASE WHEN status="QUEUED" THEN updated_at END),NOW(6)) oldest_queued_activity_seconds,
            TIMESTAMPDIFF(SECOND,MIN(CASE WHEN status="RUNNING" THEN updated_at END),NOW(6)) oldest_running_activity_seconds,
            TIMESTAMPDIFF(SECOND,MIN(CASE WHEN status="RETRY_WAIT" THEN updated_at END),NOW(6)) oldest_retry_activity_seconds
            FROM ep_processing_jobs WHERE tenant_guid=?',[$tb])?:[];
        $php=SupervisorRuntime::phpCli();$cron='* * * * * '.$php['path'].' '.SupervisorRuntime::appRoot().'/bin/scheduler.php >> '.SupervisorRuntime::appRoot().'/storage/logs/scheduler-cron.log 2>&1';
        return['tenant_guid'=>$tenant,'dispatch'=>$dispatch,'failed_outbox'=>$failures,'rabbitmq'=>$rabbit,'queues'=>$queues,'workers'=>$workers,'scheduler'=>$scheduler,'processing'=>$processing,'redis'=>$redis,'scheduler_cron_command'=>$cron,'generated_at'=>gmdate('c')];
    }

    public static function reconcileOutbox(): array{return QueueService::safeReconcile(true);}
    public static function repairMissingDispatches(): array{return QueueService::repairMissingDispatches(true);}
    public static function runScheduler(): array{return PublishingScheduler::runOnce('web');}

    public static function redisTelemetry(bool $captureBaseline=false): array
    {
        $m=RedisStore::serverMetrics();$row=Store::one('SELECT state_value,updated_at FROM ep_system_state WHERE state_key="readiness.redis_baseline"');$base=$row?json_decode((string)$row['state_value'],true):null;if(!is_array($base))$base=null;
        $current=(int)($m['evicted_keys']??0);$delta=$base!==null?max(0,$current-(int)($base['evicted_keys']??0)):null;$capturedAt=$base['captured_at']??null;
        if($captureBaseline&&($m['connected']??false)){$base=['evicted_keys'=>$current,'captured_at'=>gmdate('c'),'used_memory'=>(int)($m['used_memory']??0),'keys'=>(int)($m['keys']??0)];Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES("readiness.redis_baseline",?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',[json_encode($base,JSON_UNESCAPED_SLASHES)]);$delta=0;$capturedAt=$base['captured_at'];}
        return$m+['eviction_delta'=>$delta,'baseline_captured_at'=>$capturedAt,'baseline_available'=>$base!==null];
    }

    public static function captureRedisBaseline(): array{return self::redisTelemetry(true);}
}
