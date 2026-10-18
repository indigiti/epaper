<?php
/** Route-action compatibility seam, based on cw-epaper AI-enabled source. */
namespace EpaperSaas\Controllers;
use EpaperSaas\Core\{Auth,Build,Config,Http,RateLimiter,RedisStore,RequestContext,Store,Uuid,View};
use EpaperSaas\Services\{AnalyticsCatalog,Audit,CacheInspector,ClipService,DomainResolver,DirectRecovery,EditionOperations,EditionManifest,EditionPageSequence,EditionRemoval,AiEditorialReview,Entitlements,InfrastructureConfig,Media,DesktopAuth,DeviceAuth,MobileAuth,MobileAuthPendingException,MultiLayerCache,OfflineVault,PlatformClient,Processing,ProcessingRecovery,ProductionReadiness,PublicReadCache,PublishingRuntime,PublishingScheduler,QueueService,RecoveryCenter,RabbitMq,ReaderNavigation,ReaderSession,RuntimeCache,SchemaDoctor,SemanticNews,SeoDiscovery,SettingsCatalog,SupervisorRuntime,TenantData,TrustedAppAuth,PerformanceTelemetry};
trait SupportActions
{
    private function platformHealth(): never
    {
        $d=DomainResolver::diagnose();Http::json(['ok'=>$d['ok'],'status'=>$d['ok']?'PLATFORM_BOUND':'SETUP_REQUIRED','version'=>$this->version(),'diagnostic'=>$d,'request_guid'=>Http::requestGuid()],200);
    }

    private function queueHealth(): never
    {
        $services=[
            'RabbitMq'=>class_exists(RabbitMq::class),
            'QueueService'=>class_exists(QueueService::class),
            'InfrastructureConfig'=>class_exists(InfrastructureConfig::class),
        ];
        if(in_array(false,$services,true)){
            Http::json([
                'ok'=>false,'status'=>'QUEUE_ENGINE_INCOMPLETE','version'=>$this->version(),
                'detail'=>'RabbitMQ/Supervisor engine files are incomplete. Apply the current cumulative ePaper SaaS release package.',
                'services'=>$services,'request_guid'=>Http::requestGuid()
            ],503);
        }
        $broker=RabbitMq::instance()->ping();$stats=RabbitMq::instance()->queueStats();$last=Store::value('SELECT state_value FROM ep_system_state WHERE state_key="queue.outbox_last_run"');$outboxRun=$last?json_decode((string)$last,true):null;$workers=InfrastructureConfig::workerStatus();$safeWorkers=['ok'=>(bool)($workers['ok']??false),'expected'=>$workers['expected']??[],'live'=>$workers['live']??[],'source'=>$workers['source']??'unknown'];$ok=(bool)$broker['ok'];Http::json(['ok'=>$ok,'status'=>$ok?'QUEUE_READY':'QUEUE_ATTENTION','version'=>$this->version(),'driver'=>QueueService::driver(),'rabbitmq'=>$broker,'queues'=>$stats,'workers'=>$safeWorkers,'dependencies'=>['php_amqplib'=>class_exists('PhpAmqpLib\\Connection\\AMQPStreamConnection')],'outbox_last_run'=>is_array($outboxRun)?$outboxRun:null,'request_guid'=>Http::requestGuid()],$ok?200:503);
    }

    private function canonicalSetupRequired(\Throwable $e): never
    {
        $d=DomainResolver::diagnose();$action=(string)($d['recommended_action']??'CHECK_PLATFORM_BINDING');$domain=$d['domain']??[];$message=(string)($domain['message']??$e->getMessage());$safe=['Service token required'=>'The ePaper SaaS service token has not been configured.','Invalid service token'=>'The configured ePaper SaaS service token is not valid.','Service scope denied'=>'The service account does not have the required Platform scope.','Domain is not active'=>'The canonical ePaper domain is not ACTIVE in Digiti Platform.','Domain is not registered for EPAPER'=>'This hostname is not registered to the EPAPER product.','Resolved domain has no product tenant'=>'The Platform domain record is missing its product tenant.'];$summary=$safe[$message]??'The canonical ePaper hostname is not yet bound to an active EPAPER tenant.';$steps='<ol class="setup-steps"><li>In Digiti Platform, ensure an ACTIVE organisation and EPAPER product tenant exist.</li><li>Create an ACTIVE ePaper SaaS service account with <code>domains.resolve</code> and <code>tenant.read</code> at minimum.</li><li>Register <code>'.$this->e(Http::host()).'</code> to the ePaper SaaS application and product tenant, or configure the canonical product tenant GUID.</li><li>Confirm the domain status is <strong>ACTIVE</strong>, then retry this page.</li></ol>';$details='<div class="panel"><h2>Platform binding required</h2><p>'.$this->e($summary).'</p>'.$steps.'<p><a class="btn primary" href="/health/platform">Open safe Platform diagnostic</a> <a class="btn ghost" href="/health/ready">System readiness</a></p><p class="muted">Diagnostic action: <code>'.$this->e($action).'</code> · Request: <code>'.$this->e(Http::requestGuid()).'</code></p></div>';http_response_code(503);View::layout('Platform setup required',$details,['brand'=>'ePaper SaaS']);
    }

    private function version(): string{return Build::version();}

    private function e(mixed $v): string{return View::e($v);}

    private function csrf(): string{return '<input type="hidden" name="_csrf" value="'.$this->e(Http::csrf()).'">';}

    private function guid(?string $b): ?string{return Uuid::fromBinary($b);}

    private function brandName(): string{return (string)TenantData::brand()['site_name'];}

    private function hydrateIdentityContext(): void
    {
        $ctx=RequestContext::all();$u=Auth::user();if($u)$ctx['user_guid']=$u['user_guid']??null;$m=Auth::currentMembership();if($m){$ctx['membership_guid']=$m['membership_guid']??null;$ctx['role_key']=$m['role_key']??null;}RequestContext::set($ctx);
    }

    private function serviceAccess(): string
    {
        $k='access:'.RequestContext::tenantGuid();$cached=\EpaperSaas\Core\RedisStore::getJson($k);if($cached)return(string)($cached['service_access_state']??'FULL');try{$d=PlatformClient::instance()->access(RequestContext::tenantGuid());\EpaperSaas\Core\RedisStore::setJson($k,$d,180);return(string)($d['service_access_state']??'BLOCKED');}catch(\Throwable){return'FULL';}
    }

    private function requireDataPlane(): void
    {
        $state=$this->serviceAccess();if(in_array($state,['FULL','RESTRICTED'],true))return;http_response_code(402);View::layout('Service access restricted','<section class="empty-state"><h1>Publisher service is restricted</h1><p>This ePaper account currently has <strong>'.$this->e($state).'</strong> access. Publisher administrators can review subscription details from the ePaper account area.</p><a class="btn" href="/admin">Publisher dashboard</a></section>',['brand'=>$this->brandName()]);
    }

    private function recordMenu(string $content): string
    {
        return'<details class="record-menu"><summary aria-label="Record actions">•••</summary><div class="record-popover">'.$content.'</div></details>';
    }

    private function adminError(string $message): never{http_response_code(422);View::layout('Request needs attention','<section class="empty-state request-attention"><span class="eyebrow">REQUEST NEEDS ATTENTION</span><h1>We could not complete this change</h1><p>'.$this->e($message).'</p><p class="muted">Request: <code>'.$this->e(Http::requestGuid()).'</code></p><a class="btn" href="javascript:history.back()">Go back</a></section>',['brand'=>$this->brandName(),'admin'=>true]);}

    private function notFound(string $message): never{http_response_code(404);View::layout('Unavailable','<section class="empty-state"><h1>'.$this->e($message).'</h1><a class="btn" href="/">Back to ePaper</a></section>',['brand'=>$this->brandName()]);}

    private function slug(string $v): string{$v=mb_strtolower(trim($v));$v=preg_replace('/[^a-z0-9]+/u','-',$v);return trim((string)$v,'-');}
}
