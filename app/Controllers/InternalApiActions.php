<?php
/** Compatibility action seam for cw-platform. */
namespace Digiti\Controllers;
use Digiti\Core\{Auth,Audit,Config,Crypto,Database,DomainTools,Entitlements,Http,Mfa,OAuth,RateLimiter,RedisStore,ServiceAuth,Store,Uuid,View};
use Digiti\Billing\ProviderFactory;
use Digiti\Admin\Crud;
use Digiti\Domain\EdgeProviderFactory;
trait InternalApiActions
{
    private function consumeImpersonation(): never
    {
        ServiceAuth::requireScope('impersonation.consume');$d=Http::bodyJson();$token=$d['token']??'';$tenantGuid=$d['product_tenant_guid']??'';$pt=Store::one('SELECT organisation_guid FROM product_tenants WHERE product_tenant_guid=? AND status="ACTIVE"',[Uuid::toBinary($tenantGuid)]);if(!$pt)Http::json(['ok'=>false,'code'=>'TENANT_NOT_FOUND'],404);$grant=Store::one('SELECT impersonation_guid,admin_guid,organisation_guid,user_guid,reason,expires_at,used_at,revoked_at FROM impersonation_grants WHERE token_hash=? LIMIT 1',[hash('sha256',$token,true)]);if(!$grant||$grant['used_at']||$grant['revoked_at']||strtotime($grant['expires_at'])<time()||$grant['organisation_guid']!==$pt['organisation_guid'])Http::json(['ok'=>false,'code'=>'IMPERSONATION_INVALID'],403);$membership=Store::one('SELECT membership_guid,organisation_guid,user_guid,role_key,status FROM memberships WHERE organisation_guid=? AND user_guid=? AND status="ACTIVE"',[Uuid::toBinary($grant['organisation_guid']),Uuid::toBinary($grant['user_guid'])]);if(!$membership)Http::json(['ok'=>false,'code'=>'MEMBERSHIP_NOT_ACTIVE'],403);Store::exec('UPDATE impersonation_grants SET used_at=NOW(6) WHERE impersonation_guid=?',[Uuid::toBinary($grant['impersonation_guid'])]);$user=Store::one('SELECT user_guid,email,display_name,status FROM platform_users WHERE user_guid=?',[Uuid::toBinary($grant['user_guid'])]);Audit::log('impersonation.consume','impersonation_grant',$grant['impersonation_guid'],'SUCCESS',['product_tenant_guid'=>$tenantGuid]);Http::json(['ok'=>true,'identity'=>['user'=>$user,'membership'=>$membership,'impersonator_admin_guid'=>$grant['admin_guid'],'reason'=>$grant['reason']]]);
    }

private function resolveDomain(): never
    {
        ServiceAuth::requireScope('domains.resolve');
        $host=DomainTools::normalize($_GET['hostname']??'');
        (new DomainResolutionController(new \Digiti\Repositories\SqlActiveDomainRepository()))->resolve($host);
    }

    private function tenantInfo(string $guid): never
    {
        ServiceAuth::requireScope('tenant.read');$row=Store::one('SELECT pt.product_tenant_guid,pt.organisation_guid,pt.product_guid,pt.display_name,pt.status,o.organisation_code,o.display_name organisation_name,p.product_key,p.name product_name FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid JOIN products p ON p.product_guid=pt.product_guid WHERE pt.product_tenant_guid=? LIMIT 1',[Uuid::toBinary($guid)]);if(!$row)Http::json(['ok'=>false,'code'=>'TENANT_NOT_FOUND'],404);Http::json(['ok'=>true,'tenant'=>$row]);
    }

    private function tenantAccess(string $guid): never
    {
        ServiceAuth::requireScope('access.read');$row=Store::one('SELECT subscription_guid,status,billing_state,service_access_state,current_period_start,current_period_end FROM subscriptions WHERE product_tenant_guid=? ORDER BY created_at DESC LIMIT 1',[Uuid::toBinary($guid)]);if(!$row)Http::json(['ok'=>true,'product_tenant_guid'=>$guid,'subscription'=>null,'service_access_state'=>'BLOCKED']);Http::json(['ok'=>true,'product_tenant_guid'=>$guid,'subscription'=>$row,'service_access_state'=>$row['service_access_state']]);
    }

    private function tenantEntitlements(string $guid): never{ServiceAuth::requireScope('entitlements.read');$tenant=Store::one('SELECT product_tenant_guid,status FROM product_tenants WHERE product_tenant_guid=?',[Uuid::toBinary($guid)]);if(!$tenant)Http::json(['ok'=>false,'code'=>'TENANT_NOT_FOUND'],404);Http::json(['ok'=>true,'product_tenant_guid'=>$guid,'entitlements'=>Entitlements::effective($guid)]);}

    private function verifyMembership(): never
    {
        ServiceAuth::requireScope('identity.verify');$d=Http::bodyJson();$user=$d['user_guid']??'';$org=$d['organisation_guid']??'';try{$row=Store::one('SELECT membership_guid,organisation_guid,user_guid,role_key,status FROM memberships WHERE user_guid=? AND organisation_guid=? AND status="ACTIVE" LIMIT 1',[Uuid::toBinary($user),Uuid::toBinary($org)]);}catch(\Throwable){$row=null;}if(!$row)Http::json(['ok'=>false,'code'=>'MEMBERSHIP_NOT_ACTIVE'],404);Http::json(['ok'=>true,'membership'=>$row]);
    }

    private function recordUsage(): never
    {
        ServiceAuth::requireScope('usage.write');$d=Http::bodyJson();$tenant=$d['product_tenant_guid']??'';$featureKey=$d['feature_key']??'';$delta=(float)($d['delta']??0);$period=$d['period_key']??gmdate('Y-m');$f=Store::one('SELECT feature_guid FROM features WHERE feature_key=?',[$featureKey]);if(!$f)Http::json(['ok'=>false,'code'=>'FEATURE_NOT_FOUND'],404);$existing=Store::one('SELECT usage_guid FROM usage_meters WHERE product_tenant_guid=? AND feature_guid=? AND period_key=?',[Uuid::toBinary($tenant),Uuid::toBinary($f['feature_guid']),$period]);if($existing)Store::exec('UPDATE usage_meters SET used_value=GREATEST(0,used_value+?),updated_at=NOW(6) WHERE usage_guid=?',[$delta,Uuid::toBinary($existing['usage_guid'])]);else Store::exec('INSERT INTO usage_meters(usage_guid,product_tenant_guid,feature_guid,period_key,used_value,reserved_value,updated_at) VALUES(?,?,?,?,?,0,NOW(6))',[Uuid::toBinary(Uuid::v7()),Uuid::toBinary($tenant),Uuid::toBinary($f['feature_guid']),$period,max(0,$delta)]);Http::json(['ok'=>true]);
    }

    private function recordExternalAudit(): never
    {
        ServiceAuth::requireScope('audit.write');$d=Http::bodyJson();$tenant=$d['tenant_guid']??null;$resource=$d['resource_guid']??null;$actor=$d['actor_user_guid']??null;$membership=$d['membership_guid']??null;$imp=$d['impersonator_user_guid']??null;$request=$d['request_guid']??null;$corr=$d['correlation_guid']??null;Store::exec('INSERT INTO audit_events(audit_event_guid,tenant_guid,occurred_at,actor_type,actor_user_guid,membership_guid,impersonator_user_guid,action,resource_type,resource_guid,result,request_guid,correlation_guid,metadata) VALUES(?,?,NOW(6),?,?,?,?,?,?,?,?,?,?,?)',[Uuid::toBinary(Uuid::v7()),$tenant?Uuid::toBinary($tenant):null,$d['actor_type']??'SYSTEM',$actor?Uuid::toBinary($actor):null,$membership?Uuid::toBinary($membership):null,$imp?Uuid::toBinary($imp):null,$d['action']??'unknown',$d['resource_type']??'unknown',$resource?Uuid::toBinary($resource):null,$d['result']??'SUCCESS',$request?Uuid::toBinary($request):null,$corr?Uuid::toBinary($corr):null,json_encode($d['metadata']??[]) ]);Http::json(['ok'=>true]);
    }

    private function recordExternalError(): never
    {
        ServiceAuth::requireScope('errors.write');$d=Http::bodyJson();$guid=$d['error_guid']??Uuid::v7();$request=$d['request_guid']??null;$tenant=$d['tenant_guid']??null;$domain=$d['domain_guid']??null;$user=$d['user_guid']??null;Store::exec('INSERT INTO error_events(error_guid,occurred_at,request_guid,tenant_guid,domain_guid,user_guid,level,exception_class,message,file,line,fingerprint,metadata) VALUES(?,NOW(6),?,?,?,?,?,?,?,?,?,?,?)',[Uuid::toBinary($guid),$request?Uuid::toBinary($request):null,$tenant?Uuid::toBinary($tenant):null,$domain?Uuid::toBinary($domain):null,$user?Uuid::toBinary($user):null,$d['level']??'ERROR',$d['exception_class']??null,substr((string)($d['message']??'Application error'),0,1000),$d['file']??null,$d['line']??null,$d['fingerprint']??null,json_encode($d['metadata']??[]) ]);Http::json(['ok'=>true,'error_guid'=>$guid]);
    }

    private function recordHeartbeat(): never
    {
        ServiceAuth::requireScope('heartbeat.write');$d=Http::bodyJson();$app=Store::one('SELECT application_guid FROM applications WHERE application_key=?',[$d['application_key']??'']);if(!$app)Http::json(['ok'=>false,'code'=>'APPLICATION_NOT_FOUND'],404);Store::exec('INSERT INTO application_heartbeats(heartbeat_guid,application_guid,environment,version,status,metrics,received_at) VALUES(?,?,?,?,?,?,NOW(6))',[Uuid::toBinary(Uuid::v7()),Uuid::toBinary($app['application_guid']),$d['environment']??'production',$d['version']??null,$d['status']??'ONLINE',json_encode($d['metrics']??[]) ]);Store::exec('UPDATE applications SET current_version=?,updated_at=NOW(6) WHERE application_guid=?',[$d['version']??null,Uuid::toBinary($app['application_guid'])]);Http::json(['ok'=>true]);
    }

    private function authorize(string $method): never
    {
        $client=$_REQUEST['client_id']??'';$redirect=$_REQUEST['redirect_uri']??'';$state=$_REQUEST['state']??'';$challenge=$_REQUEST['code_challenge']??'';$app=Store::one('SELECT application_guid,name,callback_url,oauth_client_id,status FROM applications WHERE oauth_client_id=?',[$client]);if(!$app||$app['status']!=='ACTIVE'||!$redirect||!OAuth::redirectAllowed($app['application_guid'],$redirect,$app['callback_url']??null))Http::json(['ok'=>false,'code'=>'OAUTH_CLIENT_INVALID'],400);
        if($method==='POST'){Http::verifyCsrf();$bucket='oauth-login:'.($_SERVER['REMOTE_ADDR']??'unknown').':'.strtolower(trim($_POST['email']??''));if(!RateLimiter::allow($bucket,12,300)){$error='Too many sign-in attempts. Try again later.';}elseif(!OAuth::login($_POST['email']??'',$_POST['password']??'',$_POST['totp']??'')){$error='Invalid email, password or authenticator code.';}else{$user=OAuth::user();$code=OAuth::issueCode($user,$app,$redirect,$challenge,$state);$sep=str_contains($redirect,'?')?'&':'?';Http::redirect($redirect.$sep.http_build_query(['code'=>$code,'state'=>$state]));}}
        elseif($user=OAuth::user()){$code=OAuth::issueCode($user,$app,$redirect,$challenge,$state);$sep=str_contains($redirect,'?')?'&':'?';Http::redirect($redirect.$sep.http_build_query(['code'=>$code,'state'=>$state]));}
        $body='<div class="auth-card"><h2>Continue to '.$this->e($app['name']).'</h2><p>Sign in with your Digiti identity.</p>'.(!empty($error)?'<div class="alert danger">'.$this->e($error).'</div>':'').'<form method="post">'.$this->csrf().'<input type="hidden" name="client_id" value="'.$this->e($client).'"><input type="hidden" name="redirect_uri" value="'.$this->e($redirect).'"><input type="hidden" name="state" value="'.$this->e($state).'"><input type="hidden" name="code_challenge" value="'.$this->e($challenge).'"><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" required></label><label>Authenticator code <span class="muted">(if enabled)</span><input name="totp" inputmode="numeric" autocomplete="one-time-code"></label><button class="primary">Continue</button></form></div>';View::layout('Digiti Identity',$body,['subtitle'=>'Authorization code + PKCE']);
    }

    private function directAuth(): never
    {
        $service=ServiceAuth::requireScope('auth.direct');
        $d=Http::bodyJson();
        $clientId=trim((string)($d['client_id']??''));
        $tenantGuid=strtolower(trim((string)($d['product_tenant_guid']??'')));
        $email=strtolower(trim((string)($d['email']??'')));
        $password=(string)($d['password']??'');
        $totp=trim((string)($d['totp']??''));
        if($clientId===''||$tenantGuid===''||$email===''||$password==='')Http::json(['ok'=>false,'code'=>'AUTH_REQUEST_INVALID','message'=>'Invalid sign-in request'],400);
        try{$tenantBin=Uuid::toBinary($tenantGuid);}catch(\Throwable){Http::json(['ok'=>false,'code'=>'AUTH_REQUEST_INVALID','message'=>'Invalid sign-in request'],400);}
        $app=Store::one('SELECT application_guid,product_guid,application_key,name,status,oauth_client_id FROM applications WHERE oauth_client_id=? AND status="ACTIVE" LIMIT 1',[$clientId]);
        if(!$app)Http::json(['ok'=>false,'code'=>'AUTH_CLIENT_INVALID','message'=>'Application authentication is not configured'],401);
        $tenant=Store::one('SELECT pt.product_tenant_guid,pt.organisation_guid,pt.product_guid,pt.display_name,pt.status,o.status organisation_status,p.status product_status FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid JOIN products p ON p.product_guid=pt.product_guid WHERE pt.product_tenant_guid=? LIMIT 1',[$tenantBin]);
        if(!$tenant||$tenant['status']!=='ACTIVE'||$tenant['organisation_status']!=='ACTIVE'||$tenant['product_status']!=='ACTIVE')Http::json(['ok'=>false,'code'=>'TENANT_INACTIVE','message'=>'Publisher account is not active'],403);
        if(!hash_equals((string)$app['product_guid'],(string)$tenant['product_guid']))Http::json(['ok'=>false,'code'=>'AUTH_PRODUCT_MISMATCH','message'=>'Application is not authorized for this publisher'],403);
        if(!empty($service['product_guid'])&&!hash_equals((string)$service['product_guid'],(string)$app['product_guid']))Http::json(['ok'=>false,'code'=>'SERVICE_PRODUCT_MISMATCH','message'=>'Service account is not authorized for this product'],403);
        $bucket='direct-auth:'.$tenantGuid.':'.hash('sha256',$email);
        if(!RateLimiter::allow($bucket,10,300)){Audit::log('identity.direct_login_rate_limited','product_tenant',$tenantGuid,'DENIED',['application_key'=>$app['application_key']]);Http::json(['ok'=>false,'code'=>'AUTH_RATE_LIMITED','message'=>'Too many sign-in attempts. Try again later.'],429);}
        $user=OAuth::verifyCredentials($email,$password,$totp);
        if(!$user){Audit::log('identity.direct_login_failed','product_tenant',$tenantGuid,'DENIED',['application_key'=>$app['application_key'],'email_hash'=>hash('sha256',$email)]);Http::json(['ok'=>false,'code'=>'AUTH_INVALID','message'=>'Invalid email, password or authenticator code'],401);}
        $memberships=Store::all('SELECT membership_guid,organisation_guid,role_key,status FROM memberships WHERE user_guid=? AND status="ACTIVE"',[Uuid::toBinary($user['user_guid'])]);
        $identity=['user'=>$user,'memberships'=>$memberships,'authenticated_at'=>gmdate('c'),'assurance'=>!empty($user['mfa_used'])?'PASSWORD_MFA':'PASSWORD','product_tenant_guid'=>$tenantGuid,'application_guid'=>$app['application_guid']];
        unset($identity['user']['mfa_used']);
        Audit::log('identity.direct_login','product_tenant',$tenantGuid,'SUCCESS',['application_key'=>$app['application_key'],'user_guid'=>$user['user_guid']]);
        Http::json(['ok'=>true,'identity'=>$identity]);
    }

    private function authToken(): never
    {
        ServiceAuth::requireScope('auth.exchange');$d=Http::bodyJson();try{$identity=OAuth::exchange($d['code']??'',$d['code_verifier']??'',$d['client_id']??'');Http::json(['ok'=>true,'identity'=>$identity]);}catch(\Throwable$e){Http::json(['ok'=>false,'code'=>'AUTH_CODE_INVALID','message'=>$e->getMessage()],400);}
    }

    private function webhook(string $provider): never
    {
        $payload=file_get_contents('php://input')?:'';$headers=array_change_key_case(getallheaders()?:[],CASE_LOWER);$adapter=ProviderFactory::make($provider);$guid=Uuid::v7();$data=$adapter->normalizeWebhook($payload,$headers);$ref=$data['id']??($data['event']??null);$verified=$adapter->verifyWebhook($payload,$headers);try{Store::exec('INSERT INTO webhook_events(webhook_event_guid,provider,provider_event_reference,verification_status,processing_status,headers_json,payload,received_at) VALUES(?,?,?,?,"RECEIVED",?,?,NOW(6))',[Uuid::toBinary($guid),$provider,$ref,$verified?'VERIFIED':'INVALID',json_encode($headers),$payload]);}catch(\PDOException$e){if(($e->errorInfo[1]??0)===1062)Http::json(['ok'=>true,'duplicate'=>true]);throw$e;}if(!$verified)Http::json(['ok'=>false,'code'=>'WEBHOOK_SIGNATURE_INVALID'],400);Store::exec('INSERT INTO outbox_events(outbox_event_guid,event_type,payload,status,available_at,created_at) VALUES(?,"payment.webhook.received",?,"PENDING",NOW(6),NOW(6))',[Uuid::toBinary(Uuid::v7()),json_encode(['webhook_event_guid'=>$guid,'provider'=>$provider])]);Http::json(['ok'=>true]);
    }
}
