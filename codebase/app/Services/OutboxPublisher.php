<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{RequestContext,Store,Uuid};

final class OutboxPublisher
{
    public function publishBatch(int $limit = 100): array
    {
        $published=0;$failed=0;$skipped=0;
        try{Store::exec('UPDATE ep_outbox_events SET status="PENDING",locked_at=NULL,available_at=NOW(6),last_error="Recovered stale outbox publisher lock" WHERE status="PROCESSING" AND locked_at<DATE_SUB(NOW(6),INTERVAL 2 MINUTE)');}catch(\Throwable){}
        for($i=0;$i<$limit;$i++){
            $row=$this->claim();
            if(!$row)break;
            try{
                $eventType=(string)$row['event_type'];
                $p=json_decode((string)$row['payload_json'],true);
                if(!is_array($p))throw new \RuntimeException('Invalid outbox payload');
                if($eventType==='CACHE_INVALIDATE'){
                    $tenantGuid=(string)($p['tenant_guid']??'');
                    if($tenantGuid===''&&!empty($row['tenant_guid']))$tenantGuid=Uuid::fromBinary($row['tenant_guid']);
                    $tags=is_array($p['tags']??null)?$p['tags']:[];
                    if($tenantGuid!==''&&$tags)RuntimeCache::apply($tenantGuid,$tags);
                    $this->finish($row);$published++;continue;
                }
                if($eventType==='CACHE_WARM'){
                    $tenantGuid=(string)($p['tenant_guid']??'');if($tenantGuid===''&&!empty($row['tenant_guid']))$tenantGuid=Uuid::fromBinary($row['tenant_guid']);
                    if($tenantGuid!==''){
                        $tenant=Store::one('SELECT display_name,organisation_guid FROM ep_tenants WHERE tenant_guid=?',[Uuid::toBinary($tenantGuid)]);
                        if($tenant){RequestContext::set(['tenant_guid'=>$tenantGuid,'tenant_name'=>$tenant['display_name']??'Publisher','organisation_guid'=>!empty($tenant['organisation_guid'])?Uuid::fromBinary($tenant['organisation_guid']):null,'request_guid'=>Uuid::v7(),'correlation_guid'=>Uuid::v7()]);$editionGuid=(string)($p['edition_guid']??'');if($editionGuid!==''&&Uuid::isValid($editionGuid))EditionManifest::buildByGuid($editionGuid);PublicReadCache::warm();if(!empty($p['seo']))SeoDiscovery::warm();}
                    }
                    $this->finish($row);$published++;continue;
                }
                if($eventType==='QUEUE_PROBE'){
                    $routing=(string)($p['routing_key']??'');$probe=(string)($p['probe_guid']??'');$lane=(string)($p['lane']??'');
                    if(!$routing||!$probe||!$lane)throw new \RuntimeException('Outbox probe payload incomplete');
                    RabbitMq::instance()->publishJob($routing,[
                        'probe_guid'=>$probe,'lane'=>$lane,'tenant_guid'=>(string)($p['tenant_guid']??''),
                        'request_guid'=>Uuid::v7(),'correlation_guid'=>Uuid::v7(),'published_at'=>gmdate('c'),'priority'=>9,
                    ],Uuid::fromBinary($row['outbox_event_guid']));
                    $this->finish($row);$published++;continue;
                }
                if($eventType!=='QUEUE_PUBLISH'){
                    $this->finish($row);
                    $skipped++;
                    continue;
                }
                $jobGuid=(string)($p['job_guid']??'');
                $routing=(string)($p['routing_key']??'');
                if(!$jobGuid||!$routing)throw new \RuntimeException('Outbox queue payload missing job_guid/routing_key');
                $job=Store::one('SELECT job_guid,tenant_guid,edition_guid,job_type,status,attempt_count,payload_json FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',[$row['tenant_guid'],Uuid::toBinary($jobGuid)]);
                if(!$job){$this->finish($row);$skipped++;continue;}
                if(in_array((string)$job['status'],['COMPLETED','FAILED','CANCELLED','RUNNING'],true)){
                    $this->finish($row);$skipped++;continue;
                }
                $message=[
                    'job_guid'=>$jobGuid,
                    'tenant_guid'=>Uuid::fromBinary($job['tenant_guid']),
                    'edition_guid'=>$job['edition_guid']?Uuid::fromBinary($job['edition_guid']):null,
                    'job_type'=>(string)$job['job_type'],
                    'attempt'=>(int)$job['attempt_count'],
                    'request_guid'=>Uuid::v7(),
                    'correlation_guid'=>Uuid::v7(),
                    'published_at'=>gmdate('c'),
                    'priority'=>((string)($p['priority']??'normal')==='high'?9:0),
                ];
                $kind=(string)($p['exchange_kind']??'main');
                $messageId=Uuid::fromBinary($row['outbox_event_guid']);
                $rabbit=RabbitMq::instance();
                if($kind==='retry')$rabbit->publishRetry((string)($p['retry_delay']??'30s'),$routing,$message,$messageId);
                elseif($kind==='dead')$rabbit->publishDead($routing,$message,$messageId);
                else $rabbit->publishJob($routing,$message,$messageId);
                $this->finish($row);
                $published++;
            }catch(\Throwable $e){
                $this->fail($row,$e->getMessage());
                $failed++;
            }
        }
        $pending=(int)Store::value('SELECT COUNT(*) FROM ep_outbox_events WHERE status="PENDING"');
        $processing=(int)Store::value('SELECT COUNT(*) FROM ep_outbox_events WHERE status="PROCESSING"');
        $failedTotal=(int)Store::value('SELECT COUNT(*) FROM ep_outbox_events WHERE status="FAILED"');
        Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES("queue.outbox_last_run",?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',[json_encode(['published'=>$published,'failed'=>$failed,'skipped'=>$skipped,'pending'=>$pending,'processing'=>$processing,'failed_total'=>$failedTotal,'at'=>gmdate('c')],JSON_UNESCAPED_SLASHES)]);
        return compact('published','failed','skipped','pending','processing');
    }

    private function claim(): ?array
    {
        return Store::tx(function(){
            $row=Store::one('SELECT * FROM ep_outbox_events WHERE status="PENDING" AND available_at<=NOW(6) ORDER BY created_at LIMIT 1 FOR UPDATE SKIP LOCKED');
            if(!$row)return null;
            Store::exec('UPDATE ep_outbox_events SET status="PROCESSING",locked_at=NOW(6),attempt_count=attempt_count+1 WHERE tenant_guid <=> ? AND outbox_event_guid=?',[$row['tenant_guid'],$row['outbox_event_guid']]);
            $row['attempt_count']=(int)$row['attempt_count']+1;
            return $row;
        });
    }

    private function finish(array $row): void
    {
        Store::exec('UPDATE ep_outbox_events SET status="PROCESSED",processed_at=NOW(6),locked_at=NULL,last_error=NULL WHERE tenant_guid <=> ? AND outbox_event_guid=?',[$row['tenant_guid'],$row['outbox_event_guid']]);
    }

    private function fail(array $row,string $error): void
    {
        $attempt=(int)($row['attempt_count']??1);
        if($attempt>=10){
            Store::exec('UPDATE ep_outbox_events SET status="FAILED",locked_at=NULL,last_error=? WHERE tenant_guid <=> ? AND outbox_event_guid=?',[substr($error,0,1000),$row['tenant_guid'],$row['outbox_event_guid']]);
            return;
        }
        $delay=min(300,5*(2**min(5,max(0,$attempt-1))));
        $availableAt=gmdate('Y-m-d H:i:s.u',time()+$delay);
        Store::exec('UPDATE ep_outbox_events SET status="PENDING",locked_at=NULL,last_error=?,available_at=? WHERE tenant_guid <=> ? AND outbox_event_guid=?',[substr($error,0,1000),$availableAt,$row['tenant_guid'],$row['outbox_event_guid']]);
    }
}
