<?php
namespace Digiti;

use Digiti\Core\{Auth,Audit,Config,Crypto,Database,DomainTools,Entitlements,Http,Mfa,OAuth,RateLimiter,RedisStore,ServiceAuth,Store,Uuid,View};
use Digiti\Billing\ProviderFactory;
use Digiti\Admin\Crud;
use Digiti\Domain\EdgeProviderFactory;

final class App
{
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
        if($path==='/admin/billing/webhooks'&&$method==='GET')\Digiti\Presentation\Admin\WebhookReviewPage::show();
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

    private function version(): string{return trim((string)@file_get_contents(dirname(__DIR__).'/VERSION'))?:'unknown';}
    private function e(mixed $v): string{return View::esc($v);}
    private function csrf(): string{return '<input type="hidden" name="_csrf" value="'.$this->e(Http::csrf()).'">';}
    private function money(int|string $minor,string $currency='INR'): string{return $currency.' '.number_format(((int)$minor)/100,2);}
    private function status(string $s): string{return '<span class="status '.strtolower(preg_replace('/[^a-z0-9]+/i','-',$s)).'">'.$this->e($s).'</span>';}
    private function table(array $headers,array $rows): string
    {
        $h='<div class="table-wrap"><table><thead><tr>';foreach($headers as$x)$h.='<th>'.$this->e($x).'</th>';$h.='</tr></thead><tbody>';
        foreach($rows as$row){$h.='<tr>';foreach($row as$cell)$h.='<td>'.$cell.'</td>';$h.='</tr>';}$h.='</tbody></table></div>';return $h;
    }
    private function select(string $name,array $rows,string $valueKey,string $labelKey,?string $selected=null): string
    {
        $h='<select name="'.$this->e($name).'" required><option value="">Select…</option>';foreach($rows as$r){$v=(string)$r[$valueKey];$h.='<option value="'.$this->e($v).'"'.($selected===$v?' selected':'').'>'.$this->e($r[$labelKey]).'</option>';}$h.='</select>';return $h;
    }

    private function login(string $method): never
    {
        if(Auth::admin())Http::redirect('/admin');$error='';
        if($method==='POST'){Http::verifyCsrf();$bucket='platform-login:'.($_SERVER['REMOTE_ADDR']??'unknown');if(!RateLimiter::allow($bucket,10,300)){$error='Too many sign-in attempts. Try again later.';}elseif(Auth::login($_POST['email']??'',$_POST['password']??'',$_POST['totp']??'')){Audit::log('platform.login','platform_admin');Http::redirect('/admin');}else{$error='Invalid email, password or authenticator code.';}}
        $body='<div class="auth-card"><h2>Platform Administrator</h2><p>Sign in to the Digiti control plane.</p>'.($error?'<div class="alert danger">'.$this->e($error).'</div>':'').'<form method="post">'.$this->csrf().'<label>Email<input type="email" name="email" required autofocus></label><label>Password<input type="password" name="password" required></label><label>Authenticator code <span class="muted">(if enabled)</span><input name="totp" inputmode="numeric" autocomplete="one-time-code"></label><button class="primary">Sign in</button></form></div>';View::layout('Sign In',$body,['subtitle'=>'Platform control plane']);
    }

    private function dashboard(): never
    {
        $counts=\Digiti\Infrastructure\Reporting\AdminDashboardRepository::counts();
        $cards='<div class="kpis">';foreach($counts as$k=>$v)$cards.='<div class="kpi"><span>'.$this->e($k).'</span><strong>'.$v.'</strong></div>';$cards.='</div>';
        $attention=[];$badDomains=(int)Store::value('SELECT COUNT(*) FROM domains WHERE status IN ("REVALIDATION_FAILED","SUSPENDED")');if($badDomains)$attention[]="$badDomains domain(s) require attention";$failedJobs=(int)Store::value('SELECT COUNT(*) FROM jobs WHERE status="FAILED"');if($failedJobs)$attention[]="$failedJobs failed job(s)";$pastDue=(int)Store::value('SELECT COUNT(*) FROM subscriptions WHERE status="PAST_DUE"');if($pastDue)$attention[]="$pastDue past-due subscription(s)";
        $recent=Store::all('SELECT occurred_at,actor_type,action,resource_type,result FROM audit_events ORDER BY occurred_at DESC LIMIT 12');$rows=[];foreach($recent as$r)$rows[]=[ $this->e($r['occurred_at']),$this->e($r['actor_type']),$this->e($r['action']),$this->e($r['resource_type']),$this->status($r['result'])];
        $body=$cards.'<div class="grid-2"><section class="card"><h2>Needs attention</h2>'.($attention?'<ul><li>'.implode('</li><li>',array_map([$this,'e'],$attention)).'</li></ul>':'<p class="muted">No current platform alerts.</p>').'</section><section class="card"><h2>Quick access</h2><div class="actions"><a class="button" href="/admin/domains">Domains</a><a class="button" href="/admin/service-accounts">Service Accounts</a><a class="button" href="/admin/health">System Health</a></div></section></div><section class="card"><h2>Recent activity</h2>'.$this->table(['Time','Actor','Action','Resource','Result'],$rows).'</section>';View::layout('Overview',$body,['subtitle'=>'Platform health, tenants, products and operations']);
    }

    private function organisations(string $method): never
    {
        if($method==='POST'){\Digiti\Application\Admin\RegistryCreates::organisation($_POST);View::flash('Organisation created.');Http::redirect('/admin/organisations');}
        $items=Store::all('SELECT organisation_guid,organisation_code,display_name,status,created_at FROM organisations ORDER BY created_at DESC');$rows=[];foreach($items as$r)$rows[]=[$this->e($r['display_name']),'<code>'.$this->e($r['organisation_code']).'</code>',$this->status($r['status']),'<code>'.$this->e($r['organisation_guid']).'</code>',$this->e($r['created_at']),Crud::rowActions('organisation',$r['organisation_guid'],$r['status'],'/admin/organisations')];
        $form='<section class="card"><h2>Create organisation</h2><form method="post" class="form-grid">'.$this->csrf().'<label>Code<input name="code" placeholder="PUNE_MIRROR" required></label><label>Display name<input name="name" required></label><label>Legal name<input name="legal_name"></label><div class="form-action"><button class="primary">Create</button></div></form></section>';
        View::layout('Organisations',$form.'<section class="card"><h2>All organisations</h2>'.$this->table(['Name','Code','Status','GUID','Created','Actions'],$rows).'</section>',['subtitle'=>'Global customer identities shared across Digiti SaaS products']);
    }

    private function users(string $method): never
    {
        if($method==='POST'){\Digiti\Application\Admin\RegistryCreates::user($_POST);View::flash('User created.');Http::redirect('/admin/users');}
        $orgs=Store::all('SELECT organisation_guid,display_name FROM organisations WHERE status="ACTIVE" ORDER BY display_name');$items=Store::all('SELECT u.user_guid,u.email,u.display_name,u.status,COUNT(m.membership_guid) memberships FROM platform_users u LEFT JOIN memberships m ON m.user_guid=u.user_guid GROUP BY u.user_guid ORDER BY u.created_at DESC');$rows=[];foreach($items as$r)$rows[]=[$this->e($r['display_name']),$this->e($r['email']),$this->status($r['status']),$this->e($r['memberships']),'<code>'.$this->e($r['user_guid']).'</code>',Crud::rowActions('user',$r['user_guid'],$r['status'],'/admin/users')];$form='<section class="card"><h2>Create global user</h2><form method="post" class="form-grid">'.$this->csrf().'<label>Name<input name="name" required></label><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" minlength="10" required></label><label>Initial organisation'.$this->select('organisation_guid',$orgs,'organisation_guid','display_name').'</label><label>Role<select name="role_key"><option>OWNER</option><option>ADMIN</option><option>MANAGER</option><option>VIEWER</option></select></label><div class="form-action"><button class="primary">Create user</button></div></form></section>';View::layout('Users',$form.'<section class="card"><h2>Global identities</h2>'.$this->table(['Name','Email','Status','Memberships','GUID','Actions'],$rows).'</section>',['subtitle'=>'One identity can belong to multiple organisations/products']);
    }

    private function products(string $method): never
    {
        if($method==='POST'){\Digiti\Application\Admin\RegistryCreates::product($_POST);View::flash('Product registered.');Http::redirect('/admin/products');}
        $items=Store::all('SELECT p.product_guid,p.product_key,p.name,p.status,p.current_version,COUNT(a.application_guid) apps FROM (SELECT products.*,NULL current_version FROM products) p LEFT JOIN applications a ON a.product_guid=p.product_guid GROUP BY p.product_guid ORDER BY p.name');$rows=[];foreach($items as$r)$rows[]=[$this->e($r['name']),'<code>'.$this->e($r['product_key']).'</code>',$this->status($r['status']),$this->e($r['apps']),'<code>'.$this->e($r['product_guid']).'</code>',Crud::rowActions('product',$r['product_guid'],$r['status'],'/admin/products')];$form='<section class="card"><h2>Register product</h2><form method="post" class="form-grid">'.$this->csrf().'<label>Product key<input name="product_key" placeholder="CRM" required></label><label>Name<input name="name" required></label><label>Description<input name="description"></label><div class="form-action"><button class="primary">Register</button></div></form></section><div class="actions"><a class="button" href="/admin/applications">Applications</a><a class="button" href="/admin/product-tenants">Product Tenants</a></div>';View::layout('Products',$form.'<section class="card"><h2>Product registry</h2>'.$this->table(['Product','Key','Status','Apps','GUID','Actions'],$rows).'</section>',['subtitle'=>'ePaper SaaS, CRM and future Digiti SaaS products']);
    }

    private function applications(string $method): never
    {
        $products=Store::all('SELECT product_guid,name FROM products WHERE status="ACTIVE" ORDER BY name');if($method==='POST'){$client=\Digiti\Application\Admin\RegistryCreates::application($_POST);View::flash('Application registered. OAuth Client ID: '.$client);Http::redirect('/admin/applications');}
        $items=Store::all('SELECT a.application_guid,a.application_key,a.name,a.base_url,a.status,a.current_version,a.oauth_client_id,p.name product_name FROM applications a JOIN products p ON p.product_guid=a.product_guid ORDER BY p.name,a.name');$rows=[];foreach($items as$r)$rows[]=[$this->e($r['product_name']),$this->e($r['name']),'<code>'.$this->e($r['application_key']).'</code>','<a href="'.$this->e($r['base_url']).'">'.$this->e($r['base_url']).'</a>',$this->status($r['status']),$this->e($r['current_version']??'—'),'<code>'.$this->e($r['oauth_client_id']??'—').'</code>',Crud::rowActions('application',$r['application_guid'],$r['status'],'/admin/applications')];$form='<section class="card"><h2>Register application</h2><form method="post" class="form-grid">'.$this->csrf().'<label>Product'.$this->select('product_guid',$products,'product_guid','name').'</label><label>Application key<input name="application_key" placeholder="city_epaper" required></label><label>Name<input name="name" required></label><label>Base URL<input name="base_url" type="url" required></label><label>Admin URL<input name="admin_url" type="url"></label><label>Health URL<input name="health_url" type="url"></label><label>OAuth callback<input name="callback_url" type="url"></label><div class="form-action"><button class="primary">Register</button></div></form></section>';View::layout('Applications',$form.'<section class="card"><h2>Registered applications</h2>'.$this->table(['Product','Application','Key','URL','Status','Version','OAuth Client','Actions'],$rows).'</section>');
    }

    private function productTenants(string $method): never
    {
        $orgs=Store::all('SELECT organisation_guid,display_name FROM organisations WHERE status="ACTIVE" ORDER BY display_name');$products=Store::all('SELECT product_guid,name FROM products WHERE status="ACTIVE" ORDER BY name');if($method==='POST'){\Digiti\Application\Admin\RegistryCreates::tenant($_POST);View::flash('Product tenant created.');Http::redirect('/admin/product-tenants');}
        $items=Store::all('SELECT pt.product_tenant_guid,pt.display_name,pt.status,o.display_name organisation,p.name product FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid JOIN products p ON p.product_guid=pt.product_guid ORDER BY o.display_name,p.name');$rows=[];foreach($items as$r)$rows[]=[$this->e($r['organisation']),$this->e($r['product']),$this->e($r['display_name']),$this->status($r['status']),'<code>'.$this->e($r['product_tenant_guid']).'</code>',Crud::rowActions('product-tenant',$r['product_tenant_guid'],$r['status'],'/admin/product-tenants')];$form='<section class="card"><h2>Create product tenant</h2><form method="post" class="form-grid">'.$this->csrf().'<label>Organisation'.$this->select('organisation_guid',$orgs,'organisation_guid','display_name').'</label><label>Product'.$this->select('product_guid',$products,'product_guid','name').'</label><label>Display name<input name="display_name" required></label><label>External reference<input name="external_ref"></label><div class="form-action"><button class="primary">Create</button></div></form></section>';View::layout('Product Tenants',$form.'<section class="card"><h2>Tenants by product</h2>'.$this->table(['Organisation','Product','Tenant','Status','GUID','Actions'],$rows).'</section>');
    }

    private function domains(string $method): never
    {
        $tenants=Store::all('SELECT pt.product_tenant_guid,CONCAT(o.display_name," — ",p.name) label,pt.organisation_guid,pt.product_guid FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid JOIN products p ON p.product_guid=pt.product_guid WHERE pt.status="ACTIVE" ORDER BY o.display_name');$apps=Store::all('SELECT application_guid,product_guid,name FROM applications WHERE status="ACTIVE" ORDER BY name');
        if($method==='POST'){$receipt=\Digiti\Application\Admin\RegistryCreates::domain($_POST);$host=$receipt['host'];$token=$receipt['token'];View::flash('Domain added. Configure CNAME to '.Config::get('app.domains_target').' or TXT _digiti-verification.'.$host.' = '.$token);Http::redirect('/admin/domains');}
        $items=Store::all('SELECT d.domain_guid,d.hostname,d.status,d.is_primary,d.verification_token,d.last_checked_at,o.display_name organisation,p.name product,a.name application FROM domains d JOIN organisations o ON o.organisation_guid=d.organisation_guid JOIN products p ON p.product_guid=d.product_guid JOIN applications a ON a.application_guid=d.application_guid ORDER BY d.created_at DESC');$rows=[];foreach($items as$r){$action='<div class="actions compact"><a class="button small" href="/admin/domains/'.$this->e($r['domain_guid']).'/edit">Edit</a><form class="inline-form" method="post" action="/admin/domains/'.$this->e($r['domain_guid']).'/verify">'.$this->csrf().'<button class="small">Verify</button></form>'.(in_array($r['status'],['ROUTING_VALID','SSL_PROVISIONING'],true)?'<form class="inline-form" method="post" action="/admin/domains/'.$this->e($r['domain_guid']).'/activate">'.$this->csrf().'<button class="small">Activate</button></form>':'').'<form class="inline-form" method="post" action="/admin/domains/'.$this->e($r['domain_guid']).'/remove">'.$this->csrf().'<button class="small danger">Remove</button></form></div>';$rows[]=[$this->e($r['hostname']),$this->e($r['organisation']),$this->e($r['product']),$this->status($r['status']),$r['is_primary']?'Yes':'No','<code>'.$this->e($r['verification_token']).'</code>',$action];}
        $form='<section class="card"><h2>Add customer domain</h2><form method="post" class="form-grid">'.$this->csrf().'<label>Product tenant'.$this->select('product_tenant_guid',$tenants,'product_tenant_guid','label').'</label><label>Application'.$this->select('application_guid',$apps,'application_guid','name').'</label><label>Hostname<input name="hostname" placeholder="epaper.punemirror.com" required></label><label class="check"><input type="checkbox" name="is_primary" value="1"> Primary domain</label><div class="form-action"><button class="primary">Add domain</button></div></form><p class="muted">CNAME target: <code>'.$this->e(Config::get('app.domains_target')).'</code></p></section>';View::layout('Domains',$form.'<section class="card"><h2>Domain registry</h2>'.$this->table(['Hostname','Organisation','Product','Status','Primary','Verification Token','Action'],$rows).'</section>',['subtitle'=>'Global hostname → product → tenant routing']);
    }

    private function verifyDomain(string $guid): never
    {
        $result=\Digiti\Application\Routing\ManageDomain::verify($guid);
        View::flash($result['verified']?'DNS verified. Edge/TLS status: '.$result['status']:'DNS not verified yet.');
        Http::redirect('/admin/domains');
    }
    private function activateDomain(string $guid): never
    {
        \Digiti\Application\Routing\ManageDomain::activate($guid);
        View::flash('Domain activated.');
        Http::redirect('/admin/domains');
    }

    private function serviceAccounts(string $method): never
    {
        $products=Store::all('SELECT product_guid,name FROM products WHERE status="ACTIVE" ORDER BY name');if($method==='POST'){$token=\Digiti\Application\Admin\RegistryCreates::serviceAccount($_POST);View::flash('SERVICE TOKEN — copy now: '.$token);Http::redirect('/admin/service-accounts');}
        $items=Store::all('SELECT sa.service_account_guid,sa.name,sa.scopes,sa.status,sa.last_used_at,p.name product FROM service_accounts sa LEFT JOIN products p ON p.product_guid=sa.product_guid ORDER BY sa.created_at DESC');$rows=[];foreach($items as$r)$rows[]=[$this->e($r['name']),$this->e($r['product']??'Platform'),'<code>'.$this->e($r['scopes']).'</code>',$this->status($r['status']),$this->e($r['last_used_at']??'Never'),'<code>'.$this->e($r['service_account_guid']).'</code>',Crud::rowActions('service-account',$r['service_account_guid'],$r['status'],'/admin/service-accounts')];$form='<section class="card"><h2>Create service account</h2><form method="post" class="form-grid">'.$this->csrf().'<label>Name<input name="name" required></label><label>Product (optional)'.$this->select('product_guid',$products,'product_guid','name').'</label><label class="wide">Scopes<input name="scopes" value="domains.resolve,tenant.read,access.read,entitlements.read,identity.verify,impersonation.consume,usage.write,heartbeat.write,auth.exchange,audit.write,errors.write" required><small>Comma separated. Use * only for trusted platform integrations.</small></label><div class="form-action"><button class="primary">Create token</button></div></form></section>';View::layout('Service Accounts',$form.'<section class="card"><h2>Machine identities</h2>'.$this->table(['Name','Product','Scopes','Status','Last Used','GUID','Actions'],$rows).'</section>');
    }

    private function commercial(string $method): never
    {
        $catalog=\Digiti\Infrastructure\Commercial\CatalogReadRepository::snapshot();$features=$catalog['features'];$frows=[];foreach($features as$f){$actions='<div class="actions compact"><a class="button small" href="/admin/commercial/features/'.$this->e($f['feature_guid']).'/edit">Edit</a><form class="inline-form" method="post" action="/admin/commercial/features/'.$this->e($f['feature_guid']).'/remove">'.$this->csrf().'<button class="small danger">Remove</button></form></div>';$frows[]=[$this->e($f['feature_key']),$this->e($f['name']),$this->e($f['feature_type']),$this->e($f['unit']??'—'),$this->e($f['product']??'Platform'),$actions];}
        $plans=$catalog['plans'];$prows=[];foreach($plans as$p){$prices=$catalog['prices_by_plan'][$p['plan_guid']]??[];$priceHtml='';foreach($prices as$pr){$priceHtml.='<div class="price-line">'.$this->money($pr['amount_minor'],$pr['currency']).' / '.$this->e(strtolower($pr['billing_interval'])).' '.$this->status($pr['status']).'<form class="inline-form" method="post" action="/admin/commercial/prices/'.$this->e($pr['price_guid']).'/status">'.$this->csrf().'<input type="hidden" name="status" value="'.($pr['status']==='ACTIVE'?'SUSPENDED':'ACTIVE').'"><button class="small">'.($pr['status']==='ACTIVE'?'Deactivate':'Activate').'</button></form></div>';}$actions='<div class="actions compact"><a class="button small" href="/admin/commercial/plans/'.$this->e($p['plan_guid']).'/edit">Edit</a><a class="button small" href="/admin/commercial/plans/'.$this->e($p['plan_guid']).'/prices/new">Add price</a><form class="inline-form" method="post" action="/admin/commercial/plans/'.$this->e($p['plan_guid']).'/status">'.$this->csrf().'<input type="hidden" name="status" value="'.($p['status']==='ACTIVE'?'SUSPENDED':'ACTIVE').'"><button class="small">'.($p['status']==='ACTIVE'?'Deactivate':'Activate').'</button></form><form class="inline-form" method="post" action="/admin/commercial/plans/'.$this->e($p['plan_guid']).'/remove">'.$this->csrf().'<button class="small danger">Remove</button></form></div>';$prows[]=[$this->e($p['product']),$this->e($p['name']),'<code>'.$this->e($p['plan_key']).'</code>',$priceHtml?:'—',$this->status($p['status']),$actions];}
        $body='<div class="actions"><a class="button primary" href="/admin/commercial/plans/new">Add plan</a><a class="button" href="/admin/commercial/features/new">Add feature</a><a class="button" href="/admin/subscriptions">Subscriptions</a><a class="button" href="/admin/billing">Invoices & Payments</a></div><section class="card"><h2>Plans & prices</h2>'.$this->table(['Product','Plan','Key','Prices','Status','Actions'],$prows).'</section><section class="card"><h2>Feature catalogue</h2>'.$this->table(['Feature Key','Name','Type','Unit','Product','Actions'],$frows).'</section>';View::layout('Commercial',$body,['subtitle'=>'Products, plans, prices, entitlements, usage and billing controls']);
    }

    private function subscriptions(string $method): never
    {
        $tenants=Store::all('SELECT pt.product_tenant_guid,CONCAT(o.display_name," — ",p.name) label,pt.product_guid FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid JOIN products p ON p.product_guid=pt.product_guid WHERE pt.status="ACTIVE" ORDER BY o.display_name');$plans=Store::all('SELECT pl.plan_guid,pl.product_guid,CONCAT(p.name," — ",pl.name) label FROM plans pl JOIN products p ON p.product_guid=pl.product_guid WHERE pl.status="ACTIVE" ORDER BY p.name,pl.name');
        if($method==='POST'){
            \Digiti\Application\Subscriptions\ManageSubscription::activate(
                (string)($_POST['product_tenant_guid']??''),
                (string)($_POST['plan_guid']??'')
            );
            View::flash('Subscription activated.');
            Http::redirect('/admin/subscriptions');
        }
        $items=Store::all('SELECT s.subscription_guid,s.status,s.billing_state,s.service_access_state,s.current_period_end,pt.display_name tenant,pl.name plan,p.name product FROM subscriptions s JOIN product_tenants pt ON pt.product_tenant_guid=s.product_tenant_guid JOIN plans pl ON pl.plan_guid=s.plan_guid JOIN products p ON p.product_guid=pt.product_guid ORDER BY s.created_at DESC');$rows=[];foreach($items as$r)$rows[]=[$this->e($r['tenant']),$this->e($r['product']),$this->e($r['plan']),$this->status($r['status']),$this->e($r['service_access_state']),$this->e($r['current_period_end']),'<code>'.$this->e($r['subscription_guid']).'</code>','<a class="button small" href="/admin/subscriptions/'.$this->e($r['subscription_guid']).'/edit">Edit</a>'];$form='<section class="card"><h2>Activate subscription</h2><form method="post" class="form-grid">'.$this->csrf().'<label>Product tenant'.$this->select('product_tenant_guid',$tenants,'product_tenant_guid','label').'</label><label>Plan'.$this->select('plan_guid',$plans,'plan_guid','label').'</label><div class="form-action"><button class="primary">Activate</button></div></form></section>';View::layout('Subscriptions',$form.'<section class="card"><h2>Subscriptions</h2>'.$this->table(['Tenant','Product','Plan','Status','Access','Period End','GUID','Actions'],$rows).'</section>');
    }

    private function billing(string $method): never
    {
        $tenants=Store::all('SELECT pt.product_tenant_guid,CONCAT(o.display_name," — ",p.name) label FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid JOIN products p ON p.product_guid=pt.product_guid WHERE pt.status="ACTIVE" ORDER BY o.display_name');if($method==='POST'){
            $invoiceNumber=\Digiti\Application\Billing\CreateInvoice::execute(
                (string)($_POST['product_tenant_guid']??''),
                (string)($_POST['amount']??'')
            );
            View::flash('Invoice created: '.$invoiceNumber);
            Http::redirect('/admin/billing');
        }
        $invoices=Store::all('SELECT i.invoice_guid,i.invoice_number,i.currency,i.total_minor,i.status,i.due_at,pt.display_name tenant FROM invoices i JOIN product_tenants pt ON pt.product_tenant_guid=i.product_tenant_guid ORDER BY i.created_at DESC LIMIT 100');$rows=[];foreach($invoices as$i){$action=in_array($i['status'],['DRAFT','OPEN'],true)?'<form class="inline-form" method="post" action="/admin/billing/invoices/'.$this->e($i['invoice_guid']).'/void">'.$this->csrf().'<button class="small danger">Void</button></form>':'—';$rows[]=[$this->e($i['invoice_number']),$this->e($i['tenant']),$this->money($i['total_minor'],$i['currency']),$this->status($i['status']),$this->e($i['due_at']),'<code>'.$this->e($i['invoice_guid']).'</code>',$action];}$payments=Store::all('SELECT pay.provider,pay.amount_minor,pay.currency,pay.status,pay.provider_reference,pt.display_name tenant,pay.created_at FROM payments pay JOIN product_tenants pt ON pt.product_tenant_guid=pay.product_tenant_guid ORDER BY pay.created_at DESC LIMIT 50');$prows=[];foreach($payments as$p)$prows[]=[$this->e($p['tenant']),$this->e($p['provider']),$this->money($p['amount_minor'],$p['currency']),$this->status($p['status']),$this->e($p['provider_reference']??'—'),$this->e($p['created_at'])];$form='<section class="card"><h2>Create invoice</h2><form method="post" class="form-grid">'.$this->csrf().'<label>Tenant'.$this->select('product_tenant_guid',$tenants,'product_tenant_guid','label').'</label><label>Amount INR<input name="amount" type="number" step="0.01" min="0.01" required></label><div class="form-action"><button class="primary">Create invoice</button></div></form></section>';View::layout('Invoices & Payments','<div class="actions"><a class="button" href="/admin/billing/webhooks">Review payment webhooks</a></div>'.$form.'<section class="card"><h2>Invoices</h2>'.$this->table(['Invoice','Tenant','Total','Status','Due','GUID','Actions'],$rows).'</section><section class="card"><h2>Payments</h2>'.$this->table(['Tenant','Provider','Amount','Status','Reference','Created'],$prows).'</section>');
    }

    private function operations(): never
    {
        $jobs=(int)Store::value('SELECT COUNT(*) FROM jobs WHERE status="QUEUED"');$outbox=(int)Store::value('SELECT COUNT(*) FROM outbox_events WHERE status="PENDING"');$errors=(int)Store::value('SELECT COUNT(*) FROM error_events WHERE occurred_at>=CURDATE()');$body='<div class="kpis"><div class="kpi"><span>Queued Jobs</span><strong>'.$jobs.'</strong></div><div class="kpi"><span>Outbox Pending</span><strong>'.$outbox.'</strong></div><div class="kpi"><span>Errors Today</span><strong>'.$errors.'</strong></div></div><div class="actions"><a class="button" href="/admin/health">System Health</a><a class="button" href="/admin/audit">Audit</a><a class="button" href="/admin/errors">Error Center</a><a class="button" href="/admin/service-accounts">Service Accounts</a></div>';$delivery=\Digiti\Infrastructure\Reporting\InvalidationMetrics::snapshot();
        $latency=$delivery['p95_ack_ms']===null?'Not enough samples':number_format($delivery['p95_ack_ms'],1).' ms';
        $body.='<section class="card"><h2>Cross-application cache invalidation</h2><div class="kpis"><div class="kpi"><span>Pending</span><strong>'.$delivery['pending'].'</strong></div><div class="kpi"><span>Failed</span><strong>'.$delivery['failed'].'</strong></div><div class="kpi"><span>Acknowledged</span><strong>'.$delivery['acknowledged'].'</strong></div><div class="kpi"><span>p95 acknowledged</span><strong>'.$this->e($latency).'</strong></div></div><p class="muted">Signed ePaper acknowledgements, latest '.$delivery['sample_size'].' completed events. CDN/browser invalidation latency is measured separately.</p></section>';
        View::layout('Operations',$body,['subtitle'=>'Infrastructure, workers, traceability and diagnostics']);
    }

    private function audit(): never
    {
        $items=Store::all('SELECT audit_event_guid,occurred_at,actor_type,action,resource_type,result,request_guid,metadata FROM audit_events ORDER BY occurred_at DESC LIMIT 250');$rows=[];foreach($items as$r)$rows[]=[$this->e($r['occurred_at']),$this->e($r['actor_type']),$this->e($r['action']),$this->e($r['resource_type']),$this->status($r['result']),'<code>'.$this->e($r['request_guid']??'—').'</code>'];View::layout('Audit Log','<section class="card">'.$this->table(['Time','Actor','Action','Resource','Result','Request GUID'],$rows).'</section>',['subtitle'=>'Append-only platform activity trail']);
    }

    private function errors(): never
    {
        $items=Store::all('SELECT error_guid,occurred_at,level,exception_class,message,request_guid,fingerprint FROM error_events ORDER BY occurred_at DESC LIMIT 250');$rows=[];foreach($items as$r)$rows[]=[$this->e($r['occurred_at']),$this->status($r['level']),$this->e($r['exception_class']??'—'),$this->e($r['message']),'<code>'.$this->e($r['request_guid']??'—').'</code>','<code>'.$this->e($r['error_guid']).'</code>'];View::layout('Error Center','<section class="card">'.$this->table(['Time','Level','Exception','Message','Request','Error GUID'],$rows).'</section>',['subtitle'=>'Platform errors with stable request/error references']);
    }

    private function healthData(): array
    {
        $db=['ok'=>false];try{$start=microtime(true);Store::value('SELECT 1');$db=['ok'=>true,'latency_ms'=>round((microtime(true)-$start)*1000,1)];}catch(\Throwable$e){$db=['ok'=>false,'error'=>$e->getMessage()];}
        $redis=['enabled'=>RedisStore::enabled(),'ok'=>false];if(RedisStore::enabled()){try{$start=microtime(true);$pong=RedisStore::client()?->ping();$redis=['enabled'=>true,'ok'=>(bool)$pong,'latency_ms'=>round((microtime(true)-$start)*1000,1)];}catch(\Throwable$e){$redis=['enabled'=>true,'ok'=>false,'error'=>$e->getMessage()];}}
        $worker=Store::one('SELECT received_at,status,version FROM application_heartbeats ORDER BY received_at DESC LIMIT 1');$queue=['queued'=>(int)Store::value('SELECT COUNT(*) FROM jobs WHERE status="QUEUED"'),'failed'=>(int)Store::value('SELECT COUNT(*) FROM jobs WHERE status="FAILED"'),'outbox'=>(int)Store::value('SELECT COUNT(*) FROM outbox_events WHERE status="PENDING"')];return ['ok'=>$db['ok']&&(!$redis['enabled']||$redis['ok']),'version'=>$this->version(),'environment'=>Config::get('app.env'),'database'=>$db,'redis'=>$redis,'queue'=>$queue,'latest_application_heartbeat'=>$worker,'request_guid'=>Http::requestGuid()];
    }
    private function healthReady(): never{$h=$this->healthData();Http::json($h,$h['ok']?200:503);}
    private function healthPage(): never{$h=$this->healthData();$body='<div class="health"><div class="health-row"><strong>Application</strong>'.$this->status('ONLINE').'<span>v'.$this->e($h['version']).'</span></div><div class="health-row"><strong>MariaDB</strong>'.$this->status($h['database']['ok']?'ONLINE':'FAILED').'<span>'.($h['database']['latency_ms']??'—').' ms</span></div><div class="health-row"><strong>Redis</strong>'.$this->status(!$h['redis']['enabled']?'DISABLED':($h['redis']['ok']?'ONLINE':'FAILED')).'<span>'.($h['redis']['latency_ms']??'—').' ms</span></div><div class="health-row"><strong>Queue</strong>'.$this->status($h['queue']['failed']?'DEGRADED':'ONLINE').'<span>'.$h['queue']['queued'].' queued · '.$h['queue']['outbox'].' outbox</span></div></div>';View::layout('System Health',$body,['subtitle'=>'Database, Redis, queues, application heartbeats and version']);}

    private function impersonation(string $method): never
    {
        $admin=Auth::requireAdmin();$members=Store::all('SELECT m.membership_guid,m.user_guid,m.organisation_guid,m.role_key,u.email,u.display_name user_name,o.display_name organisation FROM memberships m JOIN platform_users u ON u.user_guid=m.user_guid JOIN organisations o ON o.organisation_guid=m.organisation_guid WHERE m.status="ACTIVE" ORDER BY o.display_name,u.display_name');if($method==='POST'){
            $token=\Digiti\Application\Security\IssueImpersonation::issue($admin['admin_guid'],$_POST);
            View::flash('ONE-TIME IMPERSONATION TOKEN — copy now: '.$token);
            Http::redirect('/admin/impersonation');
        }
        $rows=[];foreach($members as$m)$rows[]=[$this->e($m['organisation']),$this->e($m['user_name']),$this->e($m['email']),$this->e($m['role_key']),'<code>'.$this->e($m['membership_guid']).'</code>'];$options=[];foreach($members as$m)$options[]=['membership_guid'=>$m['membership_guid'],'label'=>$m['organisation'].' — '.$m['user_name'].' ('.$m['role_key'].')'];$body='<section class="card"><h2>Create support impersonation grant</h2><p class="muted">Tokens are one-time, tenant-bound, audited and expire after 10 minutes.</p><form method="post" class="form-grid">'.$this->csrf().'<label>Membership'.$this->select('membership_guid',$options,'membership_guid','label').'</label><label class="wide">Reason<input name="reason" minlength="8" required></label><div class="form-action"><button class="primary">Create one-time token</button></div></form></section><section class="card"><h2>Eligible memberships</h2>'.$this->table(['Organisation','User','Email','Role','Membership GUID'],$rows).'</section>';View::layout('Impersonation',$body,['subtitle'=>'Audited Platform support access']);
    }
    private function consumeImpersonation(): never
    {
        $payload=Http::bodyJson();
        $tenant=(string)($payload['product_tenant_guid']??'');
        ServiceAuth::requireTenantScope('impersonation.consume',$tenant);
        try{
            $identity=\Digiti\Application\Security\ConsumeImpersonation::consume((string)($payload['token']??''),$tenant);
            Http::json(['ok'=>true,'identity'=>$identity]);
        }catch(\DomainException $e){
            $code=$e->getMessage();
            if(!in_array($code,['TENANT_NOT_FOUND','MEMBERSHIP_NOT_ACTIVE','IMPERSONATION_INVALID'],true))$code='IMPERSONATION_INVALID';
            Http::json(['ok'=>false,'code'=>$code],$code==='TENANT_NOT_FOUND'?404:403);
        }
    }

    private function adminSecurity(string $method): never
    {
        $admin=Auth::requireAdmin();$row=Store::one('SELECT admin_guid,email,display_name,password_hash,mfa_enabled,mfa_secret_enc FROM platform_admins WHERE admin_guid=?',[Uuid::toBinary($admin['admin_guid'])]);Auth::start();if(empty($_SESSION['_admin_mfa_setup']))$_SESSION['_admin_mfa_setup']=Mfa::secret();$secret=$_SESSION['_admin_mfa_setup'];$message='';
        if($method==='POST'){
            $action=\Digiti\Application\Security\ManageMfa::change('platform_admins',$admin['admin_guid'],$_POST,$secret);
            if($action==='enable')unset($_SESSION['_admin_mfa_setup']);
            View::flash($action==='enable'?'MFA enabled for Platform Admin.':'MFA disabled.');
            Http::redirect('/admin/security');
        }
        $row=Store::one('SELECT mfa_enabled FROM platform_admins WHERE admin_guid=?',[Uuid::toBinary($admin['admin_guid'])]);$enabled=!empty($row['mfa_enabled']);$body='<section class="card"><h2>Multi-factor authentication</h2><p>Status: '.$this->status($enabled?'ACTIVE':'DISABLED').'</p>'.(!$enabled?'<p>Authenticator secret: <code>'.$this->e($secret).'</code></p><p class="muted">Add the secret to a TOTP authenticator. URI: <code>'.$this->e(Mfa::uri($secret,$admin['email'])).'</code></p><form method="post" class="form-grid">'.$this->csrf().'<input type="hidden" name="action" value="enable"><label>Current password<input type="password" name="password" required></label><label>Authenticator code<input name="totp" inputmode="numeric" required></label><div class="form-action"><button class="primary">Enable MFA</button></div></form>':'<form method="post" class="form-grid">'.$this->csrf().'<input type="hidden" name="action" value="disable"><label>Current password<input type="password" name="password" required></label><label>Authenticator code<input name="totp" inputmode="numeric" required></label><div class="form-action"><button>Disable MFA</button></div></form>').'</section>';View::layout('Security',$body,['subtitle'=>'Platform Administrator MFA and session security']);
    }
    private function userSecurity(string $method): never
    {
        $user=OAuth::user();if(!$user)View::layout('Account Security','<div class="auth-card"><h2>Sign in from your product</h2><p>Start sign-in from the SaaS application you want to access. The product will return here through the registered OAuth authorization flow.</p></div>');$row=Store::one('SELECT user_guid,email,display_name,password_hash,mfa_enabled,mfa_secret_enc FROM platform_users WHERE user_guid=?',[Uuid::toBinary($user['user_guid'])]);Auth::start();if(empty($_SESSION['_user_mfa_setup']))$_SESSION['_user_mfa_setup']=Mfa::secret();$secret=$_SESSION['_user_mfa_setup'];if($method==='POST'){
            Http::verifyCsrf();
            $action=\Digiti\Application\Security\ManageMfa::change('platform_users',$user['user_guid'],$_POST,$secret);
            if($action==='enable')unset($_SESSION['_user_mfa_setup']);
            View::flash($action==='enable'?'MFA enabled.':'MFA disabled.');
            Http::redirect('/auth/security');
        }
        $row=Store::one('SELECT mfa_enabled FROM platform_users WHERE user_guid=?',[Uuid::toBinary($user['user_guid'])]);$enabled=!empty($row['mfa_enabled']);$body='<div class="auth-card"><h2>Account security</h2><p>MFA: '.$this->status($enabled?'ACTIVE':'DISABLED').'</p>'.(!$enabled?'<p>Secret: <code>'.$this->e($secret).'</code></p><p class="muted"><code>'.$this->e(Mfa::uri($secret,$user['email'],'Digiti')).'</code></p><form method="post">'.$this->csrf().'<input type="hidden" name="action" value="enable"><label>Password<input type="password" name="password" required></label><label>Authenticator code<input name="totp" required></label><button class="primary">Enable MFA</button></form>':'<form method="post">'.$this->csrf().'<input type="hidden" name="action" value="disable"><label>Password<input type="password" name="password" required></label><label>Authenticator code<input name="totp" required></label><button>Disable MFA</button></form>').'</div>';View::layout('Account Security',$body);
    }

    private function resolveDomain(): never
    {
        $service=ServiceAuth::requireScope('domains.resolve');
        try {$host=DomainTools::normalize((string)($_GET['hostname']??''));}
        catch (\Throwable) {Http::json(['ok'=>false,'code'=>'INVALID_HOST','message'=>'Invalid hostname'],400);}
        try {$domain=\Digiti\Application\Routing\ResolveDomain::execute($host,$service);}
        catch (\Digiti\Application\Routing\DomainResolutionFailure $e) {
            Http::json(['ok'=>false,'code'=>$e->errorCode(),'message'=>$e->getMessage(),'hostname'=>$host],404);
        }
        Http::json(['ok'=>true,'cached'=>false]+$domain);
    }

    private function tenantInfo(string $guid): never
    {
        ServiceAuth::requireTenantScope('tenant.read',$guid);$row=Store::one('SELECT pt.product_tenant_guid,pt.organisation_guid,pt.product_guid,pt.display_name,pt.status,o.organisation_code,o.display_name organisation_name,p.product_key,p.name product_name FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid JOIN products p ON p.product_guid=pt.product_guid WHERE pt.product_tenant_guid=? LIMIT 1',[Uuid::toBinary($guid)]);if(!$row)Http::json(['ok'=>false,'code'=>'TENANT_NOT_FOUND'],404);Http::json(['ok'=>true,'tenant'=>$row]);
    }
    private function tenantAccess(string $guid): never
    {
        ServiceAuth::requireTenantScope('access.read',$guid);
        Http::json(\Digiti\Application\Subscriptions\SubscriptionAccess::payload($guid));
    }

    private function tenantEntitlements(string $guid): never
    {
        ServiceAuth::requireTenantScope('entitlements.read',$guid);
        $access=\Digiti\Application\Subscriptions\SubscriptionAccess::payload($guid);
        // Never expose usable plan grants when subscription or parent entity is not fully active.
        $entitlements=$access['service_access_state']==='FULL'?Entitlements::effective($guid):[];
        Http::json(['ok'=>true,'product_tenant_guid'=>$guid,'entitlements'=>$entitlements]);
    }

    private function verifyMembership(): never
    {
        ServiceAuth::requireScope('identity.verify');$d=Http::bodyJson();$user=$d['user_guid']??'';$org=$d['organisation_guid']??'';try{$row=Store::one('SELECT membership_guid,organisation_guid,user_guid,role_key,status FROM memberships WHERE user_guid=? AND organisation_guid=? AND status="ACTIVE" LIMIT 1',[Uuid::toBinary($user),Uuid::toBinary($org)]);}catch(\Throwable){$row=null;}if(!$row)Http::json(['ok'=>false,'code'=>'MEMBERSHIP_NOT_ACTIVE'],404);Http::json(['ok'=>true,'membership'=>$row]);
    }
    private function recordUsage(): never
    {
        $body=Http::bodyJson();
        $tenant=(string)($body['product_tenant_guid']??'');
        ServiceAuth::requireTenantScope('usage.write',$tenant);
        try {
            $recorded=\Digiti\Application\Metering\RecordUsage::execute($tenant,$body);
            Http::json(['ok'=>true,'duplicate'=>!$recorded]);
        } catch (\InvalidArgumentException $e) {
            Http::json(['ok'=>false,'code'=>'USAGE_REQUEST_INVALID','message'=>$e->getMessage()],400);
        } catch (\DomainException $e) {
            if($e->getMessage()==='USAGE_EVENT_CONFLICT')
                Http::json(['ok'=>false,'code'=>'USAGE_EVENT_CONFLICT'],409);
            Http::json(['ok'=>false,'code'=>'FEATURE_NOT_FOUND'],404);
        }
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
        try {
            $identity=\Digiti\Application\Identity\DirectLogin::authenticate($service,Http::bodyJson());
            Http::json(['ok'=>true,'identity'=>$identity]);
        } catch (\Digiti\Application\Identity\DirectAuthFailure $e) {
            Http::json(['ok'=>false,'code'=>$e->errorCode(),'message'=>$e->getMessage()],$e->httpStatus());
        }
    }

    private function authToken(): never
    {
        ServiceAuth::requireScope('auth.exchange');$d=Http::bodyJson();try{$identity=OAuth::exchange($d['code']??'',$d['code_verifier']??'',$d['client_id']??'');Http::json(['ok'=>true,'identity'=>$identity]);}catch(\Throwable$e){Http::json(['ok'=>false,'code'=>'AUTH_CODE_INVALID','message'=>$e->getMessage()],400);}
    }

    private function webhook(string $provider): never
    {
        $raw=(string)(file_get_contents('php://input')?:'');
        $headers=array_change_key_case(function_exists('getallheaders')?(getallheaders()?:[]):[],CASE_LOWER);
        try {
            $receipt=\Digiti\Application\Billing\WebhookIngress::receive($provider,$raw,$headers);
            Http::json($receipt);
        } catch (\Digiti\Application\Billing\WebhookFailure $e) {
            Http::json(['ok'=>false,'code'=>$e->errorCode(),'message'=>$e->getMessage()],$e->status());
        }
    }
}
