<?php
namespace Digiti;

use Digiti\Core\{Auth,Audit,Config,Crypto,Database,DomainTools,Entitlements,Http,Mfa,OAuth,RateLimiter,RedisStore,ServiceAuth,Store,Uuid,View};
use Digiti\Billing\ProviderFactory;
use Digiti\Admin\Crud;
use Digiti\Domain\EdgeProviderFactory;

final class App
{
    use \Digiti\Controllers\AdminActions;
    use \Digiti\Controllers\InternalApiActions;
    use \Digiti\Controllers\SupportActions;

    public function run(): never
    {
        $method=$_SERVER['REQUEST_METHOD']??'GET';$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
        if($path==='/health/live')Http::json(['ok'=>true,'status'=>'LIVE','version'=>$this->version(),'request_guid'=>Http::requestGuid()]);
        if($path==='/health/ready')$this->healthReady();
        if($path==='/login')$this->login($method);
        if($path==='/logout'){Auth::logout();Http::redirect('/login');}
        if($path==='/auth/authorize')$this->authorize($method);
        if($path==='/auth/security')$this->userSecurity($method);
        if($path==='/internal/v1/auth/token'&&$method==='POST')$this->authToken();
        if($path==='/internal/v1/auth/direct'&&$method==='POST')$this->directAuth();
        if($path==='/internal/v1/domains/resolve'&&$method==='GET')$this->resolveDomain();
        if(preg_match('#^/internal/v1/tenants/([0-9a-f-]{36})$#',$path,$m)&&$method==='GET')$this->tenantInfo($m[1]);
        if(preg_match('#^/internal/v1/tenants/([0-9a-f-]{36})/entitlements$#',$path,$m)&&$method==='GET')$this->tenantEntitlements($m[1]);
        if(preg_match('#^/internal/v1/tenants/([0-9a-f-]{36})/access$#',$path,$m)&&$method==='GET')$this->tenantAccess($m[1]);
        if($path==='/internal/v1/memberships/verify'&&$method==='POST')$this->verifyMembership();
        if($path==='/internal/v1/impersonation/consume'&&$method==='POST')$this->consumeImpersonation();
        if($path==='/internal/v1/usage'&&$method==='POST')$this->recordUsage();
        if($path==='/internal/v1/heartbeats'&&$method==='POST')$this->recordHeartbeat();
        if($path==='/internal/v1/audit'&&$method==='POST')$this->recordExternalAudit();
        if($path==='/internal/v1/errors'&&$method==='POST')$this->recordExternalError();
        if(preg_match('#^/webhooks/([a-z0-9_-]+)$#',$path,$m)&&$method==='POST')$this->webhook($m[1]);
        if($path==='/')Http::redirect('/admin');
        Auth::requireAdmin();
        if($method==='POST')Http::verifyCsrf();
        if(Crud::dispatch($path,$method))exit;
        if($path==='/admin')$this->dashboard();
        if($path==='/admin/organisations')$this->organisations($method);
        if($path==='/admin/users')$this->users($method);
        if($path==='/admin/products')$this->products($method);
        if($path==='/admin/applications')$this->applications($method);
        if($path==='/admin/product-tenants')$this->productTenants($method);
        if($path==='/admin/domains')$this->domains($method);
        if(preg_match('#^/admin/domains/([0-9a-f-]{36})/verify$#',$path,$m)&&$method==='POST')$this->verifyDomain($m[1]);
        if(preg_match('#^/admin/domains/([0-9a-f-]{36})/activate$#',$path,$m)&&$method==='POST')$this->activateDomain($m[1]);
        if($path==='/admin/service-accounts')$this->serviceAccounts($method);
        if($path==='/admin/commercial')$this->commercial($method);
        if($path==='/admin/subscriptions')$this->subscriptions($method);
        if($path==='/admin/billing')$this->billing($method);
        if($path==='/admin/operations')$this->operations();
        if($path==='/admin/audit')$this->audit();
        if($path==='/admin/errors')$this->errors();
        if($path==='/admin/health')$this->healthPage();
        if($path==='/admin/security')$this->adminSecurity($method);
        if($path==='/admin/impersonation')$this->impersonation($method);
        http_response_code(404);View::layout('Not Found','<div class="card"><p>The requested platform page does not exist.</p></div>');
    }


}
