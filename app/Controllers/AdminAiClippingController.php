<?php
declare(strict_types=1);
namespace EpaperSaas\Controllers;

use EpaperSaas\Core\{Auth,Http,RequestContext,View};
use EpaperSaas\Services\AiClippingReadiness;
use EpaperSaas\Views\AiClippingReadinessView;

/** Authenticated publisher diagnostic; GET only, no mutations or worker launches. */
final class AdminAiClippingController
{
    public static function index(): never
    {
        Auth::requireAdmin();
        $membership=Auth::currentMembership();
        $role=strtoupper((string)($membership['role_key']??''));
        if(!in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR'],true)){
            http_response_code(403);
            View::layout('Access denied','<section class="empty-state"><h1>Editorial permission required</h1></section>',
                ['brand'=>(string)RequestContext::get('tenant_name','ePaper SaaS'),'admin'=>true]);
        }
        Http::privateNoStore();
        $body=AiClippingReadinessView::render(AiClippingReadiness::snapshot());
        View::layout('AI Clipping readiness',$body,
            ['brand'=>(string)RequestContext::get('tenant_name','ePaper SaaS'),'admin'=>true]);
    }
}
