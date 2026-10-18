<?php
declare(strict_types=1);
namespace EpaperSaas\Controllers;

use EpaperSaas\Core\{Auth,Config,Http,RateLimiter,RequestContext,View};
use EpaperSaas\Services\{AiClippingReadiness,AiClippingWebSetup,AiClippingActivation,Audit};
use EpaperSaas\Views\AiClippingReadinessView;

final class AdminAiClippingController
{
    private static function allow(bool $mutation): void
    {
        Auth::requireAdmin();
        $membership=Auth::currentMembership();
        $role=strtoupper((string)($membership['role_key']??''));
        $allowed=$mutation?['OWNER','ADMIN']:['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR'];
        if(!in_array($role,$allowed,true)){
            http_response_code(403);
            View::layout('Access denied',
                '<section class="empty-state"><h1>Editorial administrator permission required</h1></section>',
                ['brand'=>(string)RequestContext::get('tenant_name','ePaper SaaS'),'admin'=>true]);
            exit;
        }
        Http::privateNoStore();
    }

    public static function index(): never
    {
        self::allow(false);
        $snapshot=AiClippingReadiness::snapshot();
        $membership=Auth::currentMembership();
        $snapshot['setup_can_manage']=in_array(strtoupper((string)($membership['role_key']??'')),['OWNER','ADMIN'],true);
        $body=AiClippingReadinessView::render($snapshot);
        View::layout('AI Clipping readiness',$body,
            ['brand'=>(string)RequestContext::get('tenant_name','ePaper SaaS'),'admin'=>true]);
    }

    public static function action(): never
    {
        self::allow(true);
        Http::verifyCsrf();
        $actor=(string)(Auth::user()['user_guid']??'');
        if($actor===''||!RateLimiter::allow(
            'ai-setup:'.RequestContext::tenantGuid().':'.$actor,5,300)){
            Http::json(['ok'=>false,'code'=>'SETUP_RATE_LIMIT'],429);
        }
        $action=(string)($_POST['action']??'');
        try {
            if($action==='confirm_existing_staging'){
                $result=AiClippingWebSetup::confirmExistingStaging(
                    (string)($_POST['hostname']??''),
                    (string)($_POST['database_name']??''),
                    ($_POST['backup_confirmed']??'')==='yes',
                    ($_POST['staging_confirmed']??'')==='yes',
                    $actor
                );
                Audit::log('ai.setup.existing_staging','ai_clipping_configuration',
                    'web_setup','SUCCESS',['result'=>$result,'no_ai_activation'=>true]);
            }else{
                if(!AiClippingWebSetup::gate()['allowed']){
                    Http::json(['ok'=>false,'code'=>'STAGING_ONLY',
                        'message'=>'Confirm this installation as staging in AI Clipping settings.'],403);
                }
                if($action==='apply_schema'){
                    $result=AiClippingWebSetup::applyCandidateSchema(
                        (string)($_POST['database_name']??''),
                        ($_POST['backup_confirmed']??'')==='yes'
                    );
                    Audit::log('ai.setup.schema','ai_candidate_table',
                        'ep_ai_page_candidates','SUCCESS',['result'=>$result,'no_publication'=>true]);
                }elseif($action==='test_python'){
                    $result=AiClippingWebSetup::testPython();
                    Audit::log('ai.setup.python_smoke','ai_candidate_worker',
                        'synthetic','SUCCESS',['result'=>$result,'no_live_data'=>true]);
                }elseif($action==='enable_ai'){
                    $result=AiClippingActivation::enable(($_POST['activation_confirmed']??'')==='yes');
                    Audit::log('ai.setup.enable','ai_clipping_feature',
                        'ai_clipping.enabled','SUCCESS',['result'=>$result,'editorial_only'=>true]);
                }elseif($action==='disable_ai'){
                    $result=AiClippingActivation::disable();
                    Audit::log('ai.setup.disable','ai_clipping_feature',
                        'ai_clipping.enabled','SUCCESS',['result'=>$result]);
                }else{
                    Http::json(['ok'=>false,'code'=>'ACTION_NOT_ALLOWED'],422);
                }
            }
            Http::redirect('/admin/ai-clipping?setup_result='.rawurlencode($result));
        }catch(\Throwable $e){
            $safeCodes=[
                'STAGING_CONFIRMATION_REQUIRED','PRIVATE_RUNTIME_NOT_WRITABLE','PRIVATE_RUNTIME_INVALID',
                'STAGING_ONLY_OR_CONFIRMATION_REQUIRED','MIGRATION_SOURCE_INVALID',
                'MIGRATION_VERIFICATION_FAILED','STAGING_ONLY','PYTHON_NOT_CONFIGURED',
                'SMOKE_TEST_UNAVAILABLE','WORKER_PROCESS_UNAVAILABLE',
                'WORKER_TEST_TIMED_OUT','PYTHON_SMOKE_FAILED',
                'ACTIVATION_CONFIRMATION_REQUIRED','CANDIDATE_SCHEMA_MISSING',
                'AI_SOURCE_MISSING','RABBITMQ_NOT_CONFIGURED',
                'RABBITMQ_UNAVAILABLE','OCR_WORKER_NOT_RUNNING',
                'PRIVATE_RUNTIME_NOT_WRITABLE','PRIVATE_RUNTIME_INVALID'
            ];
            $code=in_array($e->getMessage(),$safeCodes,true)?$e->getMessage():'SETUP_CHECK_FAILED';
            Http::redirect('/admin/ai-clipping?setup_result='.rawurlencode($code));
        }
    }
}
