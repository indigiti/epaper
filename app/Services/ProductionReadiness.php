<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Build,Config,RedisStore,RequestContext,Store};

/**
 * EP-DI production-readiness probes.
 *
 * These checks are deliberately read-only. They describe automated runtime
 * state without pretending that external backup/restore or browser/device QA
 * has been verified by the application itself.
 */
final class ProductionReadiness
{
    public const READY='READY';
    public const DEGRADED='DEGRADED';
    public const ACTION='ACTION_REQUIRED';
    public const REVIEW='REVIEW';

    public static function snapshot(bool $includePlatform=true): array
    {
        $checks=[];
        $add=static function(string $category,string $name,string $status,string $detail,string $action='',array $metrics=[],bool $required=true)use(&$checks):void{
            $checks[]=['category'=>$category,'name'=>$name,'status'=>$status,'detail'=>$detail,'action'=>$action,'metrics'=>$metrics,'required'=>$required];
        };

        $add('APPLICATION','Application',self::READY,'EP-DI '.Build::version().' is loaded.','', ['version'=>Build::version()]);

        try{
            $schema=SchemaDoctor::quickStatus();$ok=(bool)($schema['ok']??false);
            $add('APPLICATION','Database schema',$ok?self::READY:self::ACTION,$ok?'Current v2 schema is aligned.':'Known schema changes are missing.',$ok?'':'Open Upgrade Center and apply only the detected repair steps.');
        }catch(\Throwable $e){$add('APPLICATION','Database schema',self::ACTION,'Schema status could not be read.','Open Upgrade Center and inspect the database preflight.');}

        // MariaDB connectivity and a small latency signal.
        try{
            $t=microtime(true);$version=(string)(Store::value('SELECT VERSION()')??'unknown');$ms=round((microtime(true)-$t)*1000,1);
            $warn=max(50,(int)Config::get('observability.db_probe_warn_ms',150));$bad=max($warn+1,(int)Config::get('observability.db_probe_bad_ms',500));
            $status=$ms>=$bad?self::ACTION:($ms>=$warn?self::DEGRADED:self::READY);
            $add('CORE SERVICES','MariaDB',$status,'Connected · probe '.$ms.' ms.',$status===self::READY?'':'Inspect database load, slow queries and network latency.',['latency_ms'=>$ms,'server_version'=>$version]);
        }catch(\Throwable $e){$add('CORE SERVICES','MariaDB',self::ACTION,'Database connection failed.','Verify database credentials, service state and connectivity.');}

        $redis=RecoveryCenter::redisTelemetry(false);
        if(!RedisStore::enabled()){
            $add('CORE SERVICES','Redis',self::DEGRADED,'Redis is disabled; EP-DI will fall back to filesystem/MariaDB reads.','Enable Redis for the production multi-layer cache.',['connected'=>false]);
        }elseif(!($redis['connected']??false)){
            $add('CORE SERVICES','Redis',self::DEGRADED,'Redis is configured but unavailable. Filesystem cache fallback remains available.','Restore Redis connectivity before launch.',$redis);
        }else{
            $memoryPct=$redis['memory_percent']??null;$delta=$redis['eviction_delta']??null;$status=($memoryPct!==null&&(float)$memoryPct>=95)?self::ACTION:(($memoryPct!==null&&(float)$memoryPct>=90)||($delta!==null&&(int)$delta>0)?self::DEGRADED:self::READY);
            $detail='Connected · server '.(string)($redis['used_memory_human']??self::bytes((int)($redis['used_memory']??0))).' · DB '.(int)($redis['database']??0).' '.number_format((int)($redis['keys']??0)).' keys · EP-DI namespace '.number_format((int)($redis['namespace_keys']??0)).' key(s)';
            if(($redis['hit_rate']??null)!==null)$detail.=' · '.number_format((float)$redis['hit_rate'],1).'% hit rate';
            if($memoryPct!==null)$detail.=' · '.number_format((float)$memoryPct,1).'% server-wide maxmemory (shared Redis)';
            $detail.=' · '.number_format((int)($redis['evicted_keys']??0)).' server-wide historical evictions';
            $detail.=' · EP-DI namespace approximately '.self::bytes((int)($redis['namespace_bytes']??0)).' (bounded key scan)';
            if($delta!==null)$detail.=' · +'.number_format((int)$delta).' since baseline';else$detail.=' · no eviction baseline yet';
            $action=$status===self::READY?'':(($memoryPct!==null&&(float)$memoryPct>=90)?'Investigate server-wide Redis memory in Cloudways. Capture an eviction baseline in Web Recovery. Do not flush shared Redis.':'Open Recovery Center, capture an eviction baseline, and review recent pressure.');
            $add('CORE SERVICES','Redis',$status,$detail,$action,$redis);
        }

        if($includePlatform){
            try{$p=PlatformClient::instance()->health();$ok=(bool)($p['ok']??false);$add('CORE SERVICES','Digiti Platform',$ok?self::READY:self::ACTION,(string)($p['status']??($ok?'Connected':'Unavailable')),$ok?'':'Verify service token, domain binding and Platform availability.');}
            catch(\Throwable $e){$add('CORE SERVICES','Digiti Platform',self::ACTION,'Platform health check failed.','Verify service token, domain binding and Platform availability.');}
        }

        self::storageChecks($add);
        self::processingChecks($add);
        self::queueChecks($add);
        self::cacheChecks($add);
        self::offlineChecks($add);
        self::discoveryChecks($add);
        self::securityChecks($add);
        self::releaseChecks($add);

        // External backup verification cannot be inferred safely from local app files.
        $add('RECOVERY','Backup / restore',self::REVIEW,'Cloudways backup lifecycle is external to EP-DI and cannot be proven by a local health probe.','Before production launch, create an on-demand backup and complete one documented restore rehearsal.',[],false);
        $add('ACCEPTANCE','Browser / device QA',self::REVIEW,'Automated server probes do not replace desktop, tablet, mobile and offline-device acceptance testing.','Complete the RC acceptance matrix before promoting v2.0.0 stable.',[],false);

        $required=array_values(array_filter($checks,static fn(array $c):bool=>(bool)($c['required']??true)));
        $statuses=array_column($required,'status');
        $overall=in_array(self::ACTION,$statuses,true)?self::ACTION:(in_array(self::DEGRADED,$statuses,true)?self::DEGRADED:self::READY);
        $summary=['ready'=>0,'degraded'=>0,'action_required'=>0,'review'=>0];
        foreach($checks as$c){$k=match($c['status']){self::READY=>'ready',self::DEGRADED=>'degraded',self::ACTION=>'action_required',default=>'review'};$summary[$k]++;}
        return ['ok'=>$overall===self::READY,'status'=>$overall,'version'=>Build::version(),'summary'=>$summary,'checks'=>$checks,'generated_at'=>gmdate('c')];
    }

    private static function storageChecks(callable $add): void
    {
        $root=dirname(__DIR__,2);$storage=$root.'/storage';$writable=is_dir($storage)&&is_writable($storage);
        $free=@disk_free_space($root);$total=@disk_total_space($root);$pct=($free!==false&&$total&&$total>0)?round(($free/$total)*100,1):null;
        $warn=max(5,(float)Config::get('observability.disk_warn_percent',15));$bad=max(1,min($warn-1,(float)Config::get('observability.disk_bad_percent',8)));
        if(!$writable){$add('STORAGE','Storage',self::ACTION,'Application storage is not writable.','Restore write permissions before processing or publishing.',['free_percent'=>$pct]);}
        elseif($pct!==null&&$pct<$bad){$add('STORAGE','Storage',self::ACTION,'Only '.$pct.'% disk space remains.','Free disk space immediately; processing and cache writes are at risk.',['free_bytes'=>(int)$free,'total_bytes'=>(int)$total,'free_percent'=>$pct]);}
        elseif($pct!==null&&$pct<$warn){$add('STORAGE','Storage',self::DEGRADED,'Disk space is low: '.$pct.'% free.','Clear obsolete assets/logs or expand storage before launch.',['free_bytes'=>(int)$free,'total_bytes'=>(int)$total,'free_percent'=>$pct]);}
        else{$add('STORAGE','Storage',self::READY,'Writable'.($pct!==null?' · '.$pct.'% disk free':'').'.','',['free_bytes'=>$free===false?null:(int)$free,'total_bytes'=>$total===false?null:(int)$total,'free_percent'=>$pct]);}

        $logs=self::dirStats($storage.'/logs');$cache=self::dirStats($storage.'/cache');$tmp=self::tempStats($storage);
        $logWarn=max(50*1024*1024,(int)Config::get('observability.log_warn_bytes',500*1024*1024));
        $logStatus=$logs['bytes']>$logWarn?self::DEGRADED:self::READY;
        $add('STORAGE','Logs',$logStatus,self::bytes($logs['bytes']).' across '.number_format($logs['files']).' files.',$logStatus===self::READY?'':'Review log rotation/retention before disk pressure grows.',$logs);
        $tmpStatus=$tmp['old_files']>0?self::DEGRADED:self::READY;
        $tmpDetail=number_format($tmp['old_files']).' stale temp file(s) older than 24h · '.self::bytes($tmp['old_bytes']).'.';if(!empty($tmp['truncated']))$tmpDetail.=' Probe capped after '.number_format((int)$tmp['scanned_files']).' files; run storage maintenance for an exhaustive sweep.';
        $add('STORAGE','Temporary files',$tmpStatus,$tmpDetail,$tmpStatus===self::READY?'':'Run cache/storage maintenance and inspect interrupted processing writes.',$tmp);
        $add('STORAGE','Cache footprint',self::READY,self::bytes($cache['bytes']).' filesystem cache · '.number_format($cache['files']).' files.','',$cache,false);
    }

    private static function processingChecks(callable $add): void
    {
        foreach(['pdftoppm','tesseract'] as$cmd){$ok=self::commandExists($cmd);$add('PROCESSING',$cmd,$ok?self::READY:self::ACTION,$ok?'Available.':'Executable not found.',$ok?'':'Install/restore '.$cmd.' before processing editions.');}
        $imagick=class_exists('Imagick');$add('PROCESSING','Imagick',$imagick?self::READY:self::ACTION,$imagick?'PHP Imagick is available.':'PHP Imagick extension is unavailable.',$imagick?'':'Enable Imagick for image fallback and brand/media processing.');
        try{
            $tb=TenantData::tenantBin();$r=Store::one('SELECT
                SUM(status="QUEUED") queued,
                SUM(status="RUNNING") running,
                SUM(status="RETRY_WAIT") retry_wait,
                SUM(status="RUNNING" AND updated_at<DATE_SUB(NOW(6),INTERVAL 20 MINUTE)) stuck_running,
                SUM(status="FAILED" AND updated_at>=DATE_SUB(NOW(6),INTERVAL 1 DAY)) failed_24h,
                TIMESTAMPDIFF(SECOND,MIN(CASE WHEN status="QUEUED" THEN updated_at END),NOW(6)) oldest_queued_activity_seconds,
                TIMESTAMPDIFF(SECOND,MIN(CASE WHEN status="RUNNING" THEN updated_at END),NOW(6)) oldest_running_activity_seconds,
                TIMESTAMPDIFF(SECOND,MIN(CASE WHEN status="RETRY_WAIT" THEN updated_at END),NOW(6)) oldest_retry_activity_seconds
                FROM ep_processing_jobs WHERE tenant_guid=?',[$tb])?:[];
            $queued=(int)($r['queued']??0);$running=(int)($r['running']??0);$retry=(int)($r['retry_wait']??0);$stuck=(int)($r['stuck_running']??0);$failed=(int)($r['failed_24h']??0);$active=$queued+$running+$retry;$oldest=max((int)($r['oldest_queued_activity_seconds']??0),(int)($r['oldest_running_activity_seconds']??0),(int)($r['oldest_retry_activity_seconds']??0));
            $status=$stuck>0?self::ACTION:($failed>0?self::DEGRADED:self::READY);$detail=$active.' active · '.$queued.' queued · '.$running.' running · '.$retry.' retry wait · '.$stuck.' stuck · '.$failed.' failed in 24h';if($oldest>0)$detail.=' · oldest state activity '.self::duration($oldest);
            $add('PROCESSING','Processing backlog',$status,$detail,$stuck>0?'Open Recovery Center / Processing Center and recover stale running work.':($failed>0?'Review recent failed edition jobs; aged QUEUED creation time alone no longer blocks readiness.' : ''),['active_jobs'=>$active]+$r);
        }catch(\Throwable $e){$add('PROCESSING','Processing backlog',self::ACTION,'Processing job telemetry unavailable.','Verify the processing schema and database health.');}
    }

    private static function queueChecks(callable $add): void
    {
        if(!QueueService::rabbitEnabled()){$add('QUEUE & WORKERS','RabbitMQ',self::DEGRADED,'RabbitMQ is not the active queue driver.','Production target is RabbitMQ with Supervisor-managed workers.');}
        else{try{$broker=RabbitMq::instance()->ping();$ok=(bool)($broker['ok']??false);$add('QUEUE & WORKERS','RabbitMQ',$ok?self::READY:self::ACTION,(string)($broker['detail']??($ok?'Connected':'Unavailable')),$ok?'':'Restore broker connectivity before launch.');}catch(\Throwable $e){$add('QUEUE & WORKERS','RabbitMQ',self::ACTION,'Broker health unavailable.','Restore broker connectivity before launch.');}}

        try{
            $out=QueueService::outboxBacklog();$age=(int)($out['oldest_pending_seconds']??0);$actionable=(int)($out['failed_actionable']??0);$closeable=(int)($out['failed_closeable']??0);$missing=(int)($out['missing_dispatch_receipt']??0);$pending=(int)($out['pending']??0);$processing=(int)($out['processing']??0);
            $status=($actionable>0||$missing>0||$age>300)?self::ACTION:(($closeable>0||$age>60||$pending>100)?self::DEGRADED:self::READY);
            $detail=$pending.' pending · '.$processing.' processing · '.$actionable.' actionable failed · '.$closeable.' historical/closeable · '.$missing.' missing dispatch receipt';if($age>0)$detail.=' · oldest pending '.self::duration($age);
            $action=$status===self::READY?'':($actionable>0||$missing>0?'Open Web Recovery Center → analyze → preview → safe repair.':'Open Web Recovery Center and reconcile historical outbox state.');
            $add('QUEUE & WORKERS','Transactional outbox',$status,$detail,$action,$out);
        }catch(\Throwable $e){$add('QUEUE & WORKERS','Transactional outbox',self::ACTION,'Outbox telemetry unavailable.','Inspect QueueService and database health.');}

        try{$diag=SupervisorRuntime::diagnosis();$bad=[];foreach(($diag['lanes']??[]) as$lane=>$v)if(($v['status']??'')!=='HEALTHY')$bad[]=$lane.':'.($v['status']??'UNKNOWN');
            if(!QueueService::rabbitEnabled()){
                $add('QUEUE & WORKERS','Supervisor workers',self::DEGRADED,'Broker consumer verification deferred; current queue driver is not RabbitMQ. Existing heartbeats do not prove broker consumer readiness.','Activate and verify RabbitMQ before validating Supervisor consumer lanes.',['lanes'=>$diag['lanes']??[],'verification_deferred'=>true]);
            }else{
                $add('QUEUE & WORKERS','Supervisor workers',empty($bad)?self::READY:self::ACTION,empty($bad)?'All configured worker lanes are healthy.':'Attention: '.implode(', ',$bad),empty($bad)?'':'Open Infrastructure → Supervisor Doctor and restart the affected lane(s).',['lanes'=>$diag['lanes']??[]]);
            }
        }catch(\Throwable $e){$add('QUEUE & WORKERS','Supervisor workers',self::ACTION,'Worker diagnosis unavailable.','Open Infrastructure and run Supervisor preflight.');}

        try{
            $sch=PublishingScheduler::status();$cron=$sch['last_cron']??null;$last=$sch['last_run']??null;
            if($cron){$age=(int)$cron['age_seconds'];$status=$age<=150?self::READY:($age<=300?self::DEGRADED:self::ACTION);$detail='Last recurring cron heartbeat '.self::duration($age).' ago.';}
            elseif($last){$age=(int)$last['age_seconds'];$status=self::DEGRADED;$detail='Only legacy/manual scheduler heartbeat is visible ('.self::duration($age).' ago); recurring cron is not yet verified.';}
            else{$age=null;$status=self::ACTION;$detail='No scheduler heartbeat recorded.';}
            $add('QUEUE & WORKERS','Publishing scheduler',$status,$detail,$status===self::READY?'':'Open Web Recovery Center: run once for diagnosis, then restore/verify the one-minute cron.',['age_seconds'=>$age,'last_cron'=>$cron,'last_run'=>$last,'history'=>$sch['history']??[]]);
        }catch(\Throwable $e){$add('QUEUE & WORKERS','Publishing scheduler',self::ACTION,'Scheduler heartbeat unavailable.','Verify bin/scheduler.php and cron configuration.');}
    }

    private static function cacheChecks(callable $add): void
    {
        try{
            $c=CacheInspector::snapshot();$m=$c['manifests']??[];$multi=$c['multi']??[];$published=(int)Store::value('SELECT COUNT(*) FROM ep_editions WHERE tenant_guid=? AND status="PUBLISHED"',[TenantData::tenantBin()]);
            $pointers=(int)($m['pointers']??0);$status=$published>0&&$pointers===0?self::DEGRADED:self::READY;
            $detail=number_format($pointers).' manifest pointer(s) · '.number_format((int)($m['revisions']??0)).' revision(s) · '.number_format((int)($multi['errors']??0)).' request-cache errors';
            $add('DELIVERY','Edition manifests',$status,$detail,$status===self::READY?'':'Warm/rebuild published manifests before launch.',['published_editions'=>$published]+$m);
            $lock=(int)($multi['lock_contention']??0);$errors=(int)($multi['errors']??0);$runtimeStatus=$errors>0?self::DEGRADED:self::READY;
            $add('DELIVERY','Multi-layer cache',$runtimeStatus,'Memory/Redis/filesystem cache active · '.$lock.' lock contention event(s) in this request lifecycle.',$runtimeStatus===self::READY?'':'Inspect Redis/filesystem cache errors.',$multi);
        }catch(\Throwable $e){$add('DELIVERY','Cache & manifests',self::DEGRADED,'Cache observability unavailable.','Open Performance & Cache and verify Redis/filesystem manifest health.');}
    }

    private static function offlineChecks(callable $add): void
    {
        try{
            $tb=TenantData::tenantBin();$r=Store::one('SELECT
                (SELECT COUNT(*) FROM ep_offline_devices WHERE tenant_guid=? AND status="ACTIVE") active_devices,
                (SELECT COUNT(*) FROM ep_offline_licenses WHERE tenant_guid=? AND status<>"REVOKED" AND expires_at>NOW(6)) active_licenses,
                (SELECT COUNT(*) FROM ep_offline_licenses WHERE tenant_guid=? AND status<>"REVOKED" AND expires_at<=NOW(6)) expired_licenses',[$tb,$tb,$tb])?:[];
            $add('READER & OFFLINE','Encrypted Offline Vault',self::READY,(int)($r['active_devices']??0).' active device(s) · '.(int)($r['active_licenses']??0).' active license(s) · '.(int)($r['expired_licenses']??0).' expired license(s).','',$r);
        }catch(\Throwable $e){$add('READER & OFFLINE','Encrypted Offline Vault',self::ACTION,'Offline Vault schema/telemetry unavailable.','Open Upgrade Center and verify beta.2 Offline Vault schema.');}
    }

    private static function discoveryChecks(callable $add): void
    {
        try{
            $tb=TenantData::tenantBin();$r=Store::one('SELECT
                (SELECT COUNT(*) FROM ep_articles WHERE tenant_guid=? AND status="PUBLISHED") published_articles,
                (SELECT COUNT(*) FROM ep_authors WHERE tenant_guid=? AND status="ACTIVE") active_authors,
                (SELECT COUNT(*) FROM ep_topics WHERE tenant_guid=? AND status="ACTIVE") active_topics',[$tb,$tb,$tb])?:[];
            $articles=(int)($r['published_articles']??0);$authors=(int)($r['active_authors']??0);$status=$articles>0&&$authors===0?self::ACTION:self::READY;
            $detail=$articles.' published semantic article(s) · '.$authors.' active author(s) · '.(int)($r['active_topics']??0).' topic(s).';
            if($articles===0)$detail.=' Semantic publishing is ready but no article has been published yet.';
            $add('DISCOVERY','Semantic SEO & discovery',$status,$detail,$status===self::READY?'':'Published stories require authoritative author/newsroom profiles.',$r);
        }catch(\Throwable $e){$add('DISCOVERY','Semantic SEO & discovery',self::ACTION,'Semantic discovery schema/telemetry unavailable.','Open Upgrade Center and verify RC.1 schema.');}
    }

    private static function securityChecks(callable $add): void
    {
        $cli=PHP_SAPI==='cli';$https=$cli?null:(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off');$env=strtolower((string)Config::get('app.environment','production'));$key=(string)Config::get('app.key','');$keyStrong=$key!==''&&!str_contains($key,'CHANGE-ME')&&strlen($key)>=32;
        $status=(!$keyStrong)?self::ACTION:((!$cli&&$https!==true)?self::ACTION:($env!=='production'?self::DEGRADED:self::READY));
        $transport=$cli?'HTTPS web probe deferred':($https?'HTTPS':'HTTP');
        $detail=$transport.' · environment '.$env.' · application key '.($keyStrong?'configured':'weak/missing').' · CSP/HSTS/session hardening loaded by bootstrap.';
        $action=$status===self::READY?'':((!$cli&&$https!==true)?'Serve the canonical console only over HTTPS.':(!$keyStrong?'Set a strong application key outside source control.':'Set app.environment=production.'));
        $add('SECURITY','Security baseline',$status,$detail,$action,['https'=>$https,'https_probe'=>$cli?'DEFERRED_TO_WEB':'CHECKED','environment'=>$env,'application_key_configured'=>$keyStrong]);
        $rate=RedisStore::enabled()&&($redis=RedisStore::serverMetrics())&&($redis['connected']??false);
        $add('SECURITY','Distributed rate limiting',$rate?self::READY:self::DEGRADED,$rate?'Redis-backed rate limiting is available.':'Rate limiter will fall back to per-session counters while Redis is unavailable.',$rate?'':'Restore Redis before launch so throttling is shared across PHP workers.');
    }

    private static function releaseChecks(callable $add): void
    {
        $root=dirname(__DIR__,2);$version=Build::version();$sw=$root.'/public_html/service-worker.js';$swText=is_file($sw)?(string)@file_get_contents($sw):'';$aligned=str_contains($swText,"epdi-v{$version}");
        $manifest=is_file($root.'/SHA256SUMS');$release=is_file($root.'/BUILD-MANIFEST-'.$version.'.md');
        $status=$aligned&&$manifest&&$release?self::READY:self::ACTION;
        $detail='Service worker '.($aligned?'aligned':'mismatch').' · SHA256SUMS '.($manifest?'present':'missing').' · build manifest '.($release?'present':'missing').'.';
        $add('RELEASE','Release artifact integrity',$status,$detail,$status===self::READY?'':'Version-matched verifiable release manifests are missing from this Git checkout. Open Release Audit; do not use placeholder files to bypass verification.',['service_worker_aligned'=>$aligned,'sha256_manifest'=>$manifest,'build_manifest'=>$release]);
    }

    private static function commandExists(string $command): bool
    {
        if(!function_exists('exec'))return false;$out=[];$code=127;try{exec('command -v '.escapeshellarg($command).' 2>/dev/null',$out,$code);}catch(\Throwable){return false;}return$code===0;
    }

    private static function dirStats(string $path): array
    {
        if(!is_dir($path))return['files'=>0,'bytes'=>0,'path'=>$path];$files=0;$bytes=0;try{$it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS));foreach($it as$f)if($f->isFile()){$files++;$bytes+=(int)$f->getSize();}}catch(\Throwable){}return['files'=>$files,'bytes'=>$bytes,'path'=>$path];
    }

    private static function tempStats(string $storage): array
    {
        // Readiness must never turn into an unbounded walk over a large edition library.
        // Cap the scan and report truncation so operators know when storage maintenance
        // should perform the exhaustive cleanup instead.
        $old=0;$bytes=0;$scanned=0;$limit=5000;$truncated=false;$cut=time()-86400;
        if(!is_dir($storage))return['old_files'=>0,'old_bytes'=>0,'scanned_files'=>0,'truncated'=>false];
        try{
            $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($storage,\FilesystemIterator::SKIP_DOTS));
            foreach($it as$f){
                if(!$f->isFile())continue;
                if(++$scanned>$limit){$truncated=true;break;}
                $name=$f->getFilename();
                if(($f->getMTime()<$cut)&&(str_contains($name,'.tmp')||str_ends_with($name,'.part')||str_contains($name,'.tmp.'))){$old++;$bytes+=(int)$f->getSize();}
            }
        }catch(\Throwable){}
        return['old_files'=>$old,'old_bytes'=>$bytes,'scanned_files'=>$scanned,'truncated'=>$truncated];
    }

    private static function bytes(int $bytes): string
    {
        if($bytes<1024)return$bytes.' B';$units=['KB','MB','GB','TB'];$v=$bytes/1024;foreach($units as$u){if($v<1024)return number_format($v,$v<10?1:0).' '.$u;$v/=1024;}return number_format($v,1).' PB';
    }
    private static function duration(int $seconds): string
    {
        if($seconds<60)return$seconds.' sec';if($seconds<3600)return(int)floor($seconds/60).' min';if($seconds<86400)return number_format($seconds/3600,1).' h';return number_format($seconds/86400,1).' d';
    }
}
