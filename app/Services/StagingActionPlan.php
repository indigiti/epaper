<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

/** Read-only recommended operator actions, never a declaration of functional PASS. */
final class StagingActionPlan
{
    /** @param list<array<string,mixed>> $checks
        @return list<array{priority:int,title:string,status:string,detail:string,url:string}> */
    public static function fromChecks(array $checks,bool $aiEnabled): array
    {
        $byName=[];
        foreach($checks as $check){
            if(is_array($check)&&isset($check['name']))$byName[(string)$check['name']]=$check;
        }
        $status=static fn(string $name):string=>(string)($byName[$name]['status']??'REVIEW');
        $items=[];
        if($status('Redis')!=='READY')$items[]=[
            'priority'=>1,'title'=>'Investigate shared Redis pressure','status'=>$status('Redis'),
            'detail'=>'Memory, hit rate and evictions are server-wide. Use Web Recovery to capture the eviction baseline. Review Cloudways maxmemory; never flush shared Redis.',
            'url'=>'/admin/system/recovery',
        ];
        $rabbitReady=$status('RabbitMQ')==='READY';
        if(!$rabbitReady)$items[]=[
            'priority'=>2,'title'=>'Verify and activate RabbitMQ','status'=>$status('RabbitMQ'),
            'detail'=>'Use Infrastructure to test broker credentials and the queue connection before activating it. Do not switch drivers blindly.',
            'url'=>'/admin/system/infrastructure',
        ];
        if($status('Supervisor workers')!=='READY')$items[]=[
            'priority'=>3,'title'=>'Verify Supervisor workers',
            'status'=>$rabbitReady?$status('Supervisor workers'):'WAITING_ON_RABBITMQ',
            'detail'=>$rabbitReady?'Inspect render, assets, OCR and finalize consumers.':'RabbitMQ must be active before broker consumers can be verified. Worker heartbeat alone is not enough.',
            'url'=>'/admin/system/infrastructure/supervisor',
        ];
        if($status('Release artifact integrity')!=='READY')$items[]=[
            'priority'=>4,'title'=>'Verify complete release integrity',
            'status'=>$status('Release artifact integrity'),
            'detail'=>'The Git checkout lacks the required release checksums or build manifest. Inspect Release Audit and obtain genuine release evidence; do not fabricate a PASS.',
            'url'=>'/admin/system/release-audit',
        ];
        if(!$aiEnabled)$items[]=[
            'priority'=>5,'title'=>'Prepare staged AI editorial review','status'=>'DISABLED',
            'detail'=>'AI candidate processing is feature-flagged off. Verify migration, Python worker and role permissions before enabling it.',
            'url'=>'/admin/ai-review',
        ];
        if(!$items)$items[]=[
            'priority'=>6,'title'=>'Perform functional acceptance','status'=>'NOT_TESTED',
            'detail'=>'Infrastructure readiness cannot prove PDF publishing, Reader, clipping, AI editing, page sequence, access suspension or backup restore.',
            'url'=>'/admin/editions',
        ];
        return $items;
    }
}
