<?php
declare(strict_types=1);
namespace EpaperSaas\Controllers;

use EpaperSaas\Core\{Auth,Config,Http,RateLimiter,RequestContext,View};
use EpaperSaas\Services\{AiClippingReadiness,AiClippingWebSetup,Audit};
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
        $body=AiClippingReadinessView::render(AiClippingReadiness::snapshot());
        View::layout('AI Clipping readiness',$body,
            ['brand'=>(string)RequestContext::get('tenant_name','ePaper SaaS'),'admin'=>true]);
    }

    public static function action(): never
    {
        self::allow(true);
        Http::verifyCsrf();
        if(!AiClippingWebSetup::gate()['allowed']) {
            Http::json(['ok'=>false,'code'=>'STAGING_ONLY',
                'message'=>'This action is available only on an isolated staging ePaper installation.'],403);
        }
        $actor=(string)(Auth::user()['user_guid']??'');
        if($actor===''||!RateLimiter::allow(
            'ai-setup:'.RequestContext::tenantGuid().':'.$actor,5,300)){
            Http::json(['ok'=>false,'code'=>'SETUP_RATE_LIMIT'],429);
        }
        $action=(string)($_POST['action']??'');
        try{
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
            }else{
                Http::json(['ok'=>false,'code'=>'ACTION_NOT_ALLOWED'],422);
            }
            Http::redirect('/admin/ai-clipping?setup_result='.rawurlencode($result));
        }catch(\Throwable $e){
            // Do not reveal DB internals, command output, private paths or credentials.
            $safeCodes=['STAGING_ONLY_OR_CONFIRMATION_REQUIRED','MIGRATION_SOURCE_INVALID',
                'MIGRATION_VERIFICATION_FAILED','STAGING_ONLY','PYTHON_NOT_CONFIGURED',
                'SMOKE_TEST_UNAVAILABLE','WORKER_PROCESS_UNAVAILABLE',
                'WORKER_TEST_TIMED_OUT','PYTHON_SMOKE_FAILED'];
            $code=in_array($e->getMessage(),$safeCodes,true)?$e->getMessage():'SETUP_CHECK_FAILED';
            Http::redirect('/admin/ai-clipping?setup_result='.rawurlencode($code));
        }
    }
}
