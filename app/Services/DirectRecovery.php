<?php
namespace EpaperSaas\Services;
use EpaperSaas\Core\{Config,RequestContext,Store,Uuid};

/** Durable requests processed independently of RabbitMQ dispatch. */
final class DirectRecovery
{
    private static function key(string $tenant,string $edition): string{return 'recovery.request.'.$tenant.'.'.$edition;}
    public static function status(string $edition): array
    {
        $raw=Store::value('SELECT state_value FROM ep_system_state WHERE state_key=?',[self::key(RequestContext::tenantGuid(),$edition)]);
        return json_decode((string)$raw,true)?:[];
    }
    private static function save(array $r): void
    {
        Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES(?,?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',[self::key($r['tenant'],$r['edition']),json_encode($r,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)]);
    }
    public static function request(string $edition): array
    {
        return EditionOperations::locked($edition,function()use($edition){
            $row=Store::one('SELECT status FROM ep_editions WHERE tenant_guid=? AND edition_guid=?',[TenantData::tenantBin(),Uuid::toBinary($edition)]);
            if(!$row)throw new \RuntimeException('Edition not found');EditionOperations::assertStartState($row['status']);
            if(EditionOperations::stopIntent($edition))throw new \RuntimeException('Cancellation is pending. Complete cancellation before reprocessing.');
            $old=self::status($edition);
            if(in_array($old['status']??'', ['QUEUED','RUNNING','WAITING'],true))return $old;
            if(!EditionOperations::active($edition)){
                QueueService::maybeFinalize($edition);
                if(!EditionOperations::active($edition))throw new \RuntimeException('No unfinished jobs remain. Use Reprocess to start a new complete attempt.');
            }
            $r=['tenant'=>RequestContext::tenantGuid(),'edition'=>$edition,'status'=>'QUEUED','requested_at'=>gmdate('c'),'last_activity'=>gmdate('c'),'processed'=>0];self::save($r);
            Audit::log('edition.direct_recovery_requested','edition',$edition);return $r;
        });
    }
    /** Browser request launches CLI work, never runs PDF work inside PHP-FPM. */
    public static function launch(): bool
    {
        if(!(bool)Config::get('processing.direct_launch',true)||!function_exists('exec'))return false;
        $lock='epdi_direct_launch';if((int)Store::value('SELECT GET_LOCK(?,0)',[$lock])!==1)return false;
        try{
            $last=Store::value('SELECT state_value FROM ep_system_state WHERE state_key="recovery.launch_at"');
            if($last&&(time()-(int)$last)<10)return true;
            $php=SupervisorRuntime::phpCli();if(empty($php['found'])||empty($php['path'])||empty($php['version'])||version_compare($php['version'],'8.1','<'))return false;
            $root=dirname(__DIR__,2);$log=$root.'/storage/logs/direct-recovery.log';@mkdir(dirname($log),0775,true);
            if(!is_writable(dirname($log)))return false;
            $command=escapeshellarg((string)$php['path']).' '.escapeshellarg($root.'/bin/direct-recovery-worker.php').' --once --max-seconds=1800';
            $out=[];$code=1;exec('nohup '.$command.' >> '.escapeshellarg($log).' 2>&1 < /dev/null &',$out,$code);
            if($code!==0)return false;
            Store::exec('INSERT INTO ep_system_state(state_key,state_value,updated_at) VALUES("recovery.launch_at",?,NOW(6)) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value),updated_at=NOW(6)',[(string)time()]);
            return true; // Launch attempted; RUNNING/heartbeat, not this return value, confirms execution.
        }finally{Store::value('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
    public static function tick(): int
    {
        $rows=Store::all('SELECT state_value FROM ep_system_state WHERE state_key LIKE "recovery.request.%" AND JSON_UNQUOTE(JSON_EXTRACT(state_value,"$.status")) IN ("QUEUED","RUNNING","WAITING") ORDER BY updated_at LIMIT 20');$done=0;
        foreach($rows as$row){
            $r=json_decode($row['state_value'],true);if(!is_array($r)||!in_array($r['status']??'', ['QUEUED','RUNNING','WAITING'],true))continue;
            $old=RequestContext::all();RequestContext::set(['tenant_guid'=>$r['tenant']]);
            $lock='epdi_direct_'.substr(hash('sha256',$r['tenant'].'|'.$r['edition']),0,40);
            if((int)Store::value('SELECT GET_LOCK(?,0)',[$lock])!==1){RequestContext::set($old);continue;}
            try{
                if(EditionOperations::stopIntent($r['edition'])){$r['status']='CANCELLED';self::save($r);continue;}
                $e=Store::value('SELECT status FROM ep_editions WHERE tenant_guid=? AND edition_guid=?',[TenantData::tenantBin(),Uuid::toBinary($r['edition'])]);
                if(!in_array($e,['DRAFT','PROCESSING','NEEDS_ATTENTION','READY'],true)){$r['status']=$e==='PUBLISHED'?'COMPLETED':'STOPPED';self::save($r);continue;}
                QueueService::maybeFinalize($r['edition']);
                $jobs=EditionOperations::active($r['edition']);
                if(!$jobs){if($e==='READY')PublishingScheduler::publishEditionIfDue($r['edition']);$r['status']=$e==='READY'?'COMPLETED':'NEEDS_ATTENTION';$r['last_activity']=gmdate('c');self::save($r);continue;}
                $r['status']='WAITING';
                foreach($jobs as$j){
                    if($j['status']!=='RUNNING'&&!(bool)$j['is_due']){$r['message']='Waiting for the scheduled retry time.';continue;}
                    $r['status']='RUNNING';$r['job_guid']=Uuid::fromBinary($j['job_guid']);$r['last_activity']=gmdate('c');self::save($r);
                    $result=JobExecutor::run($r['tenant'],$r['job_guid'],(gethostname()?:'host').':direct:'.getmypid(),null,true);
                    if(in_array($result['outcome'],['BUSY','NOT_DUE'],true)){$r['status']='WAITING';$r['message']='Another worker owns this job or its retry is not due. No parallel attempt was started.';continue;}
                    $r['message']='Processing unfinished work directly.';$r['processed']++;$done++;$r['last_outcome']=$result['outcome'];$r['status']='QUEUED';break;
                }
                $r['last_activity']=gmdate('c');self::save($r);
            }catch(\Throwable $e){$r['status']='NEEDS_ATTENTION';$r['error']=substr($e->getMessage(),0,500);self::save($r);}
            finally{Store::value('SELECT RELEASE_LOCK(?)',[$lock]);RequestContext::set($old);}
        }
        return $done;
    }
    public static function pending(): int
    {
        return (int)Store::value('SELECT COUNT(*) FROM ep_system_state WHERE state_key LIKE "recovery.request.%" AND JSON_UNQUOTE(JSON_EXTRACT(state_value,"$.status")) IN ("QUEUED","RUNNING","WAITING")');
    }
}
