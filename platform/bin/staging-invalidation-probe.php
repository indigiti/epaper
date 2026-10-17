<?php
declare(strict_types=1);
$root=require dirname(__DIR__).'/app/bootstrap.php';
use Digiti\Application\Integration\CacheInvalidationOutbox;
use Digiti\Core\{Config,Store,Uuid};

if(!in_array((string)Config::get('app.env','production'),['staging','test','development'],true)||getenv('EPDI_STAGING_INVALIDATION')!=='allow'){
    fwrite(STDERR,"Denied. Staging only: set app.env=staging and EPDI_STAGING_INVALIDATION=allow.\n");
    exit(2);
}
$args=[];
foreach(array_slice($argv,1) as $arg)if(preg_match('/^--([a-z-]+)=(.+)$/',$arg,$m))$args[$m[1]]=$m[2];
$tenant=(string)($args['tenant']??'');
$samples=min(20,max(1,(int)($args['samples']??5)));
$timeout=min(60,max(5,(int)($args['timeout']??20)));
if(!Uuid::isValid($tenant))throw new InvalidArgumentException('Provide --tenant=<staging EPAPER tenant GUID>');
$results=[];
for($i=0;$i<$samples;$i++){
    $start=microtime(true);
    $ids=Store::tx(static fn():array=>CacheInvalidationOutbox::stageForTenant($tenant));
    if(!$ids)throw new RuntimeException('No EPAPER invalidation events generated (check product key)');
    $pending=array_fill_keys($ids,true);$failed=false;
    do{
        foreach(array_keys($pending) as $event){
            $row=Store::one('SELECT status,last_error FROM outbox_events WHERE outbox_event_guid=?',[Uuid::toBinary($event)]);
            $status=$row['status']??'MISSING';
            if($status==='PROCESSED')unset($pending[$event]);
            elseif(in_array($status,['FAILED','MISSING'],true)){$failed=true;break;}
        }
        if($failed||!$pending)break;
        usleep(100000);
    }while(microtime(true)-$start<$timeout);
    $latency=round((microtime(true)-$start)*1000,1);
    $results[]=['sample'=>$i+1,'milliseconds'=>$latency,'acknowledged'=>!$pending&&!$failed,'remaining'=>count($pending)];
}
$good=array_column(array_filter($results,static fn(array $row):bool=>$row['acknowledged']),'milliseconds');
sort($good,SORT_NUMERIC);
$p95=$good?(float)$good[max(0,(int)ceil(count($good)*0.95)-1)]:null;
echo json_encode(['tenant_guid'=>$tenant,'sample_count'=>count($results),
    'acknowledged'=>count($good),'failed_or_timed_out'=>count($results)-count($good),
    'p95_ack_ms'=>$p95,'results'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
exit(count($good)===count($results)?0:1);
