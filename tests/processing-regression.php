<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EPDI_TEST_DATABASE')!=='epdi_release_test'){fwrite(STDERR,"Run only in a disposable checkout with EPDI_TEST_DATABASE=epdi_release_test and EPDI_TEST_DSN.\n");exit(2);}
ob_start();session_start();$root=dirname(__DIR__);
spl_autoload_register(function($c)use($root){if(str_starts_with($c,'EpaperSaas\\')){$f=$root.'/app/'.str_replace('\\','/',substr($c,11)).'.php';if(is_file($f))require $f;}});
use EpaperSaas\Core\{Store,Database,Config,RequestContext,Uuid};
use EpaperSaas\Services\{EditionOperations as Ops,QueueService as Q,DirectRecovery as Direct,JobExecutor as Jobs,TenantData,ProcessingSummary};
$dsn=(string)getenv('EPDI_TEST_DSN');if(!str_contains($dsn,'dbname=epdi_release_test'))throw new RuntimeException('Dedicated test database required');
$pdo=new PDO($dsn,(string)getenv('EPDI_TEST_USER'),(string)getenv('EPDI_TEST_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$pdo->exec(file_get_contents($root.'/database/schema.sql'));
(new ReflectionProperty(Database::class,'pdo'))->setValue(null,$pdo);
$config=['queue'=>['driver'=>'rabbitmq'],'redis'=>['enabled'=>false],'platform'=>['url'=>'http://127.0.0.1:9'],'processing'=>['ocr_languages'=>'eng'],'db'=>['host'=>'127.0.0.1','port'=>13316,'name'=>'epdi_release_test','user'=>(string)getenv('EPDI_TEST_USER'),'pass'=>(string)getenv('EPDI_TEST_PASS')]];
(new ReflectionProperty(Config::class,'data'))->setValue(null,$config);
date_default_timezone_set('Asia/Kolkata');
$n=0;function check($yes,$msg){global $n;if(!$yes)throw new RuntimeException('FAIL '.$msg);$n++;echo "PASS $msg\n";}
function blocked($f,$msg){try{$f();}catch(RuntimeException $e){check(true,$msg);return;}check(false,$msg);}
$t=Uuid::v7();$pub=Uuid::v7();RequestContext::set(['tenant_guid'=>$t]);$tb=Uuid::toBinary($t);
Store::exec('INSERT INTO ep_tenants(tenant_guid,display_name,created_at,updated_at) VALUES(?,"Regression",NOW(6),NOW(6))',[$tb]);
Store::exec('INSERT INTO ep_publications(publication_guid,tenant_guid,publication_code,name,slug,created_at,updated_at) VALUES(?,?,"TEST","Regression","regression",NOW(6),NOW(6))',[Uuid::toBinary($pub),$tb]);
function edition($state='DRAFT'){global $tb,$pub;$g=Uuid::v7();Store::exec('INSERT INTO ep_editions(edition_guid,tenant_guid,publication_guid,edition_date,title,slug,status,created_at,updated_at) VALUES(?,?,?,"2026-09-10","Regression edition",?,?,NOW(6),NOW(6))',[Uuid::toBinary($g),$tb,Uuid::toBinary($pub),$g,$state]);return $g;}
function state($g){return Store::value('SELECT status FROM ep_editions WHERE edition_guid=?',[Uuid::toBinary($g)]);}
function job($g){global $tb;return Store::one('SELECT * FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',[$tb,Uuid::toBinary($g)]);}
$g=edition();$j=Q::enqueueEditionRender($g);check(state($g)==='PROCESSING','root admission');blocked(fn()=>Q::enqueueEditionRender($g),'overlapping root rejected');
foreach(['PUBLISHED','SCHEDULED','ARCHIVED','TRASHED'] as$s){$x=edition($s);blocked(fn()=>Direct::request($x),'direct recovery protects '.$s);}
$before=Store::value('SELECT COUNT(*) FROM ep_outbox_events');Direct::request($g);Direct::request($g);Ops::recover($g);check(Store::value('SELECT COUNT(*) FROM ep_outbox_events')===$before,'repeated recovery adds no broker messages');
check(Direct::pending()>=1,'durable direct request recorded');
$other=new PDO($dsn,(string)getenv('EPDI_TEST_USER'),(string)getenv('EPDI_TEST_PASS'));$l=Ops::jobLock($j);$s=$other->prepare('SELECT GET_LOCK(?,0)');$s->execute([$l]);
check(Jobs::run($t,$j,'test-direct',null,true)['outcome']==='BUSY','live worker lock respected');Ops::requestStop($g,'trash');check(state($g)==='PROCESSING','Trash waits for lock owner');$s=$other->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$l]);check(Jobs::run($t,$j,'test-direct',null,true)['outcome']==='CANCELLED','cancel checkpoint');check(state($g)==='TRASHED','last cancellation settles Trash');
Direct::tick();check(Direct::status($g)['status']==='CANCELLED','direct request records cancellation');
$x=edition();$rootJob=Q::enqueueEditionRender($x);Store::exec('UPDATE ep_processing_jobs SET status="COMPLETED" WHERE job_guid=?',[Uuid::toBinary($rootJob)]);
$child=Q::enqueue('PAGE_ASSETS',$x,['pipeline_guid'=>$rootJob]);Store::exec('UPDATE ep_processing_jobs SET status="RUNNING",updated_at=NOW(6) WHERE job_guid=?',[Uuid::toBinary($child)]);
$result=Jobs::run($t,$child,'test-direct',null,true);check($result['outcome']==='FAILED','recent abandoned RUNNING job executed rather than acknowledged');check(str_contains(job($child)['payload_json'],'ABANDONED_CLAIM'),'abandoned job trace retained');
check(Jobs::run($t,$child,'test-rabbit','assets')['outcome']==='TERMINAL','late broker duplicate is terminal');
$retry=Q::enqueue('PAGE_OCR',$x,['pipeline_guid'=>$rootJob,'page_guid'=>Uuid::v7(),'page_number'=>1]);$count=Store::value('SELECT COUNT(*) FROM ep_outbox_events');
$result=Jobs::run($t,$retry,'test-direct',null,true);check($result['outcome']==='QUEUED','transient direct failure schedules database retry');check(Store::value('SELECT COUNT(*) FROM ep_outbox_events')===$count,'direct retry requires no broker publish');
$remaining=(int)Store::value('SELECT TIMESTAMPDIFF(SECOND,NOW(6),available_at) FROM ep_processing_jobs WHERE job_guid=?',[Uuid::toBinary($retry)]);check($remaining>20&&$remaining<=30,'retry uses database time under IST PHP timezone');check(Jobs::run($t,$retry,'test-direct',null,true)['outcome']==='NOT_DUE','retry delay respected');
$y=edition();$yj=Q::enqueueEditionRender($y);$a=Q::enqueuePageJob('PAGE_ASSETS',$y,Uuid::v7(),1,$yj);Store::exec('UPDATE ep_processing_jobs SET status="COMPLETED" WHERE job_guid=?',[Uuid::toBinary($a)]);Q::maybeFinalize($y,$yj);check((int)Store::value('SELECT COUNT(*) FROM ep_processing_jobs WHERE edition_guid=? AND job_type="EDITION_FINALIZE"',[Uuid::toBinary($y)])===0,'no finalizer before render completion');
Store::exec('UPDATE ep_processing_jobs SET status="COMPLETED" WHERE job_guid=?',[Uuid::toBinary($yj)]);Q::maybeFinalize($y,$yj);Q::maybeFinalize($y,$yj);check((int)Store::value('SELECT COUNT(*) FROM ep_processing_jobs WHERE edition_guid=? AND job_type="EDITION_FINALIZE"',[Uuid::toBinary($y)])===1,'missing finalizer reconciled exactly once');
RequestContext::set(['tenant_guid'=>Uuid::v7()]);blocked(fn()=>Direct::request($x),'cross-tenant direct request rejected');RequestContext::set(['tenant_guid'=>$t]);
$z=edition();$zj=Q::enqueueEditionRender($z);Store::exec('UPDATE ep_processing_jobs SET updated_at=DATE_SUB(NOW(6),INTERVAL 25 MINUTE) WHERE job_guid=?',[Uuid::toBinary($zj)]);$rows=TenantData::editionsForAdmin('e.edition_guid=?',[Uuid::toBinary($z)],25);check($rows[0]['processing_stalled']===true,'queued job marked stalled');check(isset($rows[0]['stage_summary']),'batched progress summary');
$method=new ReflectionMethod(new EpaperSaas\App(),'editionTable');$html=$method->invoke(new EpaperSaas\App(),$rows);check(str_contains($html,'aria-label="Recover unfinished work"')&&str_contains($html,'aria-label="Cancel processing"')&&str_contains($html,'/process-immediately'),'accessible icon actions rendered');
check(count(TenantData::editionsForAdmin('1=1',[],2,2))===2,'pagination still works');
if(getenv('EPDI_TEST_PDF')){
    // Exercise real PDF rendering, Imagick assets, Tesseract OCR and finalization, with RabbitMQ configured but absent.
    $e=edition();$rel='uploads/regression-'.$e.'.pdf';@mkdir($root.'/storage/uploads',0775,true);copy(getenv('EPDI_TEST_PDF'),$root.'/storage/'.$rel);Store::exec('UPDATE ep_editions SET source_pdf_path=? WHERE edition_guid=?',[$rel,Uuid::toBinary($e)]);
    $rj=Q::enqueueEditionRender($e,'normal',['processing_overrides'=>['pdf_dpi'=>96,'preview_width'=>640,'thumbnail_width'=>120,'ocr_languages'=>'eng']]);Direct::request($e);
    // Restrict fixture runner to this request so deliberately failing earlier fixtures do not consume this pass.
    Store::exec('UPDATE ep_system_state SET state_value=JSON_SET(state_value,"$.status","STOPPED") WHERE state_key LIKE "recovery.request.%" AND state_key<>?',['recovery.request.'.$t.'.'.$e]);
    for($i=0;$i<10&&state($e)!=='READY';$i++)Direct::tick();
    if(state($e)!=='READY')echo json_encode(Store::all('SELECT job_type,status,error_message,payload_json FROM ep_processing_jobs WHERE edition_guid=?',[Uuid::toBinary($e)]),JSON_PRETTY_PRINT)."\n";
    check(state($e)==='READY','actual PDF to READY through direct runner with no RabbitMQ connection');
    check((int)Store::value('SELECT COUNT(*) FROM ep_page_assets a JOIN ep_pages p ON p.page_guid=a.page_guid WHERE p.edition_guid=? AND a.asset_type IN ("HIGHRES","THUMB","PREVIEW","TILE_MANIFEST")',[Uuid::toBinary($e)])>=4,'reader assets generated');
    check((int)Store::value('SELECT COUNT(*) FROM ep_page_ocr o JOIN ep_pages p ON p.page_guid=o.page_guid WHERE p.edition_guid=? AND o.status="READY"',[Uuid::toBinary($e)])>0,'actual OCR stored');
    $pageIds=Store::all('SELECT page_guid FROM ep_pages WHERE edition_guid=? ORDER BY page_number',[Uuid::toBinary($e)]);
    Store::exec('UPDATE ep_processing_jobs SET status="RUNNING" WHERE job_guid=?',[Uuid::toBinary($rj)]);$resume=Jobs::run($t,$rj,'interrupted-render',null,true);
    check($resume['outcome']==='COMPLETED'&&$pageIds===Store::all('SELECT page_guid FROM ep_pages WHERE edition_guid=? ORDER BY page_number',[Uuid::toBinary($e)]),'completed render handoff resumes after interruption without replacing pages');
    $runner=new EpaperSaas\Services\ProcessingPipeline();$method=new ReflectionMethod($runner,'runProcess');$started=microtime(true);blocked(fn()=>$method->invoke($runner,['/bin/sleep','10'],15,function(){throw new EpaperSaas\Services\CancelledJobException('test cancellation');}),'subprocess cancellation propagated');check(microtime(true)-$started<3,'cancelled subprocess terminated promptly');
    Store::exec('UPDATE ep_editions SET publish_mode="AUTO_READY" WHERE edition_guid=?',[Uuid::toBinary($e)]);Direct::tick();check(state($e)==='PUBLISHED','direct recovery honours Auto publish without cron');
    Direct::tick();
    $future=edition('SCHEDULED');Store::exec('UPDATE ep_editions SET publish_mode="SCHEDULED",scheduled_at=DATE_ADD(NOW(6),INTERVAL 1 DAY) WHERE edition_guid=?',[Uuid::toBinary($future)]);check(!EpaperSaas\Services\PublishingScheduler::publishEditionIfDue($future)&&state($future)==='SCHEDULED','future schedule is not published early');
    if(getenv('EPDI_TEST_LAUNCH')==='1'){
        if(is_file($root.'/config/config.php'))throw new RuntimeException('Launch test refuses to overwrite existing configuration');
        file_put_contents($root.'/config/config.php','<?php return '.var_export($config,true).';');
        try{
            $launchEdition=edition();Store::exec('UPDATE ep_editions SET source_pdf_path=?,publish_mode="AUTO_READY" WHERE edition_guid=?',[$rel,Uuid::toBinary($launchEdition)]);Q::enqueueEditionRender($launchEdition,'normal',['processing_overrides'=>['pdf_dpi'=>96,'preview_width'=>640,'thumbnail_width'=>120,'ocr_languages'=>'eng']]);Direct::request($launchEdition);
            check(Direct::launch(),'web action launches detached PHP CLI runner');
            $until=time()+35;while(time()<$until&&state($launchEdition)!=='PUBLISHED')usleep(250000);
            check(state($launchEdition)==='PUBLISHED','detached runner processes real PDF and publishes without RabbitMQ/Supervisor/cron');
        }finally{unlink($root.'/config/config.php');}
    }
}

echo "TOTAL $n PASSED\n";
