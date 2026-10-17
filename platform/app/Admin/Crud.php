<?php
namespace Digiti\Admin;

use Digiti\Core\{Audit,Http,RedisStore,Store,Uuid,View};

final class Crud
{
    public static function dispatch(string $path,string $method): bool
    {
        $routes = [
            '#^/admin/organisations/([0-9a-f-]{36})/edit$#' => 'organisationEdit',
            '#^/admin/organisations/([0-9a-f-]{36})/status$#' => 'organisationStatus',
            '#^/admin/organisations/([0-9a-f-]{36})/remove$#' => 'organisationRemove',
            '#^/admin/users/([0-9a-f-]{36})/edit$#' => 'userEdit',
            '#^/admin/users/([0-9a-f-]{36})/status$#' => 'userStatus',
            '#^/admin/users/([0-9a-f-]{36})/remove$#' => 'userRemove',
            '#^/admin/products/([0-9a-f-]{36})/edit$#' => 'productEdit',
            '#^/admin/products/([0-9a-f-]{36})/status$#' => 'productStatus',
            '#^/admin/products/([0-9a-f-]{36})/remove$#' => 'productRemove',
            '#^/admin/applications/([0-9a-f-]{36})/edit$#' => 'applicationEdit',
            '#^/admin/applications/([0-9a-f-]{36})/status$#' => 'applicationStatus',
            '#^/admin/applications/([0-9a-f-]{36})/remove$#' => 'applicationRemove',
            '#^/admin/product-tenants/([0-9a-f-]{36})/edit$#' => 'tenantEdit',
            '#^/admin/product-tenants/([0-9a-f-]{36})/status$#' => 'tenantStatus',
            '#^/admin/product-tenants/([0-9a-f-]{36})/remove$#' => 'tenantRemove',
            '#^/admin/domains/([0-9a-f-]{36})/edit$#' => 'domainEdit',
            '#^/admin/domains/([0-9a-f-]{36})/remove$#' => 'domainRemove',
            '#^/admin/service-accounts/([0-9a-f-]{36})/edit$#' => 'serviceAccountEdit',
            '#^/admin/service-accounts/([0-9a-f-]{36})/status$#' => 'serviceAccountStatus',
            '#^/admin/service-accounts/([0-9a-f-]{36})/rotate$#' => 'serviceAccountRotate',
            '#^/admin/service-accounts/([0-9a-f-]{36})/remove$#' => 'serviceAccountRemove',
            '#^/admin/commercial/features/new$#' => 'featureNew',
            '#^/admin/commercial/features/([0-9a-f-]{36})/edit$#' => 'featureEdit',
            '#^/admin/commercial/features/([0-9a-f-]{36})/remove$#' => 'featureRemove',
            '#^/admin/commercial/plans/new$#' => 'planNew',
            '#^/admin/commercial/plans/([0-9a-f-]{36})/edit$#' => 'planEdit',
            '#^/admin/commercial/plans/([0-9a-f-]{36})/status$#' => 'planStatus',
            '#^/admin/commercial/plans/([0-9a-f-]{36})/remove$#' => 'planRemove',
            '#^/admin/commercial/plans/([0-9a-f-]{36})/prices/new$#' => 'priceNew',
            '#^/admin/commercial/prices/([0-9a-f-]{36})/status$#' => 'priceStatus',
            '#^/admin/subscriptions/([0-9a-f-]{36})/edit$#' => 'subscriptionEdit',
            '#^/admin/billing/invoices/([0-9a-f-]{36})/void$#' => 'invoiceVoid',
        ];
        foreach ($routes as $pattern=>$handler) {
            if (preg_match($pattern,$path,$m)) {
                self::$handler($m[1]??null,$method);
                return true;
            }
        }
        return false;
    }

    private static function e(mixed $v): string { return View::esc($v); }
    private static function csrf(): string { return '<input type="hidden" name="_csrf" value="'.self::e(Http::csrf()).'">'; }
    private static function status(string $s): string { return '<span class="status '.strtolower(preg_replace('/[^a-z0-9]+/i','-',$s)).'">'.self::e($s).'</span>'; }
    private static function option(string $value,string $label,?string $selected=null): string { return '<option value="'.self::e($value).'"'.($selected===$value?' selected':'').'>'.self::e($label).'</option>'; }
    private static function selectRows(string $name,array $rows,string $valueKey,string $labelKey,?string $selected=null,bool $required=true): string
    {
        $h='<select name="'.self::e($name).'"'.($required?' required':'').'><option value="">Select…</option>';
        foreach($rows as$r){$v=(string)$r[$valueKey];$h.=self::option($v,(string)$r[$labelKey],$selected);}return $h.'</select>';
    }
    private static function actionForm(string $action,string $label,string $class='small',array $extra=[]): string
    {
        $h='<form method="post" action="'.self::e($action).'" class="inline-form">'.self::csrf();
        foreach($extra as$k=>$v)$h.='<input type="hidden" name="'.self::e($k).'" value="'.self::e($v).'">';
        return $h.'<button class="'.self::e($class).'">'.self::e($label).'</button></form>';
    }
    private static function redirect(string $path,string $message): never { View::flash($message); Http::redirect($path); }
    private static function guidBin(string $guid): string { return Uuid::toBinary($guid); }
    private static function allowed(string $value,array $allowed,string $fallback): string { return in_array($value,$allowed,true)?$value:$fallback; }
    private static function invalidateDomainsBy(string $column,string $guid): void
    {
        $allowed=['organisation_guid','product_guid','application_guid','product_tenant_guid'];if(!in_array($column,$allowed,true))return;
        foreach(Store::all('SELECT hostname FROM domains WHERE '.$column.'=?',[self::guidBin($guid)]) as $d)RedisStore::del('domain:'.$d['hostname']);
    }

    public static function rowActions(string $entity,string $guid,string $status,string $base): string
    {
        $active=$status==='ACTIVE';$h='<div class="actions compact"><a class="button small" href="'.$base.'/'.$guid.'/edit">Edit</a>';
        if($entity==='domain')return $h.self::actionForm($base.'/'.$guid.'/remove','Remove','small danger').'</div>';
        if($entity==='service-account'){
            $h.=self::actionForm($base.'/'.$guid.'/status',$active?'Revoke':'Activate','small', ['status'=>$active?'REVOKED':'ACTIVE']);
            $h.=self::actionForm($base.'/'.$guid.'/rotate','Rotate token','small');
            return $h.self::actionForm($base.'/'.$guid.'/remove','Remove','small danger').'</div>';
        }
        $h.=self::actionForm($base.'/'.$guid.'/status',$active?'Deactivate':'Activate','small', ['status'=>$active?'SUSPENDED':'ACTIVE']);
        return $h.self::actionForm($base.'/'.$guid.'/remove','Remove','small danger').'</div>';
    }

    private static function organisationEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT organisation_guid,organisation_code,legal_name,display_name,status FROM organisations WHERE organisation_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Organisation not found.');
        if($method==='POST'){
            \Digiti\Application\Admin\RegistryMutations::organisation('edit',$guid,$_POST);
            self::redirect('/admin/organisations','Organisation updated.');
        }
        $body='<section class="card"><h2>Edit organisation</h2><form method="post" class="form-grid">'.self::csrf().'<label>Code<input name="code" value="'.self::e($r['organisation_code']).'" required></label><label>Display name<input name="display_name" value="'.self::e($r['display_name']).'" required></label><label>Legal name<input name="legal_name" value="'.self::e($r['legal_name']).'"></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('SUSPENDED','SUSPENDED',$r['status']).self::option('REMOVED','REMOVED',$r['status']).'</select></label><div class="form-action"><button class="primary">Update organisation</button><a class="button" href="/admin/organisations">Cancel</a></div></form></section>';View::layout('Edit Organisation',$body,['subtitle'=>$r['organisation_code']]);
    }
    private static function organisationStatus(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/organisations');
        \Digiti\Application\Admin\RegistryMutations::organisation('status',$guid,$_POST);
        self::redirect('/admin/organisations','Organisation status updated.');
    }
    private static function organisationRemove(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/organisations');
        \Digiti\Application\Admin\RegistryMutations::organisation('remove',$guid,$_POST);
        self::redirect('/admin/organisations','Organisation removed from active registry. Historical data was preserved.');
    }

    private static function userEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT user_guid,email,display_name,status,mfa_enabled FROM platform_users WHERE user_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('User not found.');
        if($method==='POST'){
            \Digiti\Application\Admin\RegistryMutations::user('edit',$guid,$_POST);
            self::redirect('/admin/users','User updated.');
        }
        $memberships=Store::all('SELECT m.membership_guid,m.role_key,m.status,o.display_name organisation FROM memberships m JOIN organisations o ON o.organisation_guid=m.organisation_guid WHERE m.user_guid=? ORDER BY o.display_name',[self::guidBin($guid)]);$m='';foreach($memberships as$x)$m.='<tr><td>'.self::e($x['organisation']).'</td><td>'.self::e($x['role_key']).'</td><td>'.self::status($x['status']).'</td></tr>';if(!$m)$m='<tr><td colspan="3" class="muted">No memberships.</td></tr>';
        $body='<section class="card"><h2>Edit global user</h2><form method="post" class="form-grid">'.self::csrf().'<label>Name<input name="display_name" value="'.self::e($r['display_name']).'" required></label><label>Email<input type="email" name="email" value="'.self::e($r['email']).'" required></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('SUSPENDED','SUSPENDED',$r['status']).self::option('REMOVED','REMOVED',$r['status']).'</select></label><label>New password <span class="muted">optional</span><input type="password" name="new_password" minlength="10"></label><div class="form-action"><button class="primary">Update user</button><a class="button" href="/admin/users">Cancel</a></div></form></section><section class="card"><h2>Memberships</h2><div class="table-wrap"><table><thead><tr><th>Organisation</th><th>Role</th><th>Status</th></tr></thead><tbody>'.$m.'</tbody></table></div></section>';View::layout('Edit User',$body,['subtitle'=>$r['email']]);
    }
    private static function userStatus(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/users');
        \Digiti\Application\Admin\RegistryMutations::user('status',$guid,$_POST);
        self::redirect('/admin/users','User status updated.');
    }
    private static function userRemove(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/users');
        \Digiti\Application\Admin\RegistryMutations::user('remove',$guid,$_POST);
        self::redirect('/admin/users','User removed. Identity history was preserved.');
    }

    private static function productEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT product_guid,product_key,name,status,description FROM products WHERE product_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Product not found.');
        if($method==='POST'){
            \Digiti\Application\Admin\RegistryMutations::product('edit',$guid,$_POST);
            self::redirect('/admin/products','Product updated.');
        }
        $body='<section class="card"><h2>Edit product</h2><form method="post" class="form-grid">'.self::csrf().'<label>Product key<input value="'.self::e($r['product_key']).'" readonly><small>Stable integration key; create a new product instead of changing this key.</small></label><label>Name<input name="name" value="'.self::e($r['name']).'" required></label><label class="wide">Description<textarea name="description" rows="4">'.self::e($r['description']).'</textarea></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('SUSPENDED','SUSPENDED',$r['status']).self::option('REMOVED','REMOVED',$r['status']).'</select></label><div class="form-action"><button class="primary">Update product</button><a class="button" href="/admin/products">Cancel</a></div></form></section>';View::layout('Edit Product',$body,['subtitle'=>$r['product_key']]);
    }
    private static function productStatus(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/products');
        \Digiti\Application\Admin\RegistryMutations::product('status',$guid,$_POST);
        self::redirect('/admin/products','Product status updated.');
    }
    private static function productRemove(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/products');
        \Digiti\Application\Admin\RegistryMutations::product('remove',$guid,$_POST);
        self::redirect('/admin/products','Product removed from active registry. Related records were deactivated, not deleted.');
    }

    private static function applicationEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT application_guid,product_guid,application_key,name,base_url,admin_url,health_url,callback_url,oauth_client_id,integration_mode,status,current_version FROM applications WHERE application_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Application not found.');
        if($method==='POST'){
            \Digiti\Application\Admin\RegistryMutations::application('edit',$guid,$_POST);
            self::redirect('/admin/applications','Application updated.');
        }
        $body='<section class="card"><h2>Edit application</h2><form method="post" class="form-grid">'.self::csrf().'<label>Application key<input value="'.self::e($r['application_key']).'" readonly></label><label>Name<input name="name" value="'.self::e($r['name']).'" required></label><label>Base URL<input name="base_url" type="url" value="'.self::e($r['base_url']).'" required></label><label>Admin URL<input name="admin_url" type="url" value="'.self::e($r['admin_url']).'"></label><label>Health URL<input name="health_url" type="url" value="'.self::e($r['health_url']).'"></label><label>OAuth callback<input name="callback_url" type="url" value="'.self::e($r['callback_url']).'"></label><label>Integration mode<select name="integration_mode">'.self::option('REMOTE_API','REMOTE_API',$r['integration_mode']).self::option('INTERNAL_API','INTERNAL_API',$r['integration_mode']).self::option('LOCAL_MODULE','LOCAL_MODULE',$r['integration_mode']).'</select></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('SUSPENDED','SUSPENDED',$r['status']).self::option('REMOVED','REMOVED',$r['status']).'</select></label><div class="form-action"><button class="primary">Update application</button><a class="button" href="/admin/applications">Cancel</a></div></form><p class="muted">OAuth Client ID: <code>'.self::e($r['oauth_client_id']).'</code> · Current version: '.self::e($r['current_version']??'—').'</p></section>';View::layout('Edit Application',$body,['subtitle'=>$r['application_key']]);
    }
    private static function applicationStatus(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/applications');
        \Digiti\Application\Admin\RegistryMutations::application('status',$guid,$_POST);
        self::redirect('/admin/applications','Application status updated.');
    }
    private static function applicationRemove(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/applications');
        \Digiti\Application\Admin\RegistryMutations::application('remove',$guid,$_POST);
        self::redirect('/admin/applications','Application removed from active registry.');
    }

    private static function tenantEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT pt.product_tenant_guid,pt.display_name,pt.external_tenant_reference,pt.status,o.display_name organisation,p.name product FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid JOIN products p ON p.product_guid=pt.product_guid WHERE pt.product_tenant_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Product tenant not found.');
        if($method==='POST'){
            \Digiti\Application\Tenants\ManageTenant::edit($guid,$_POST);
            self::redirect('/admin/product-tenants','Product tenant updated.');
        }
        $body='<section class="card"><h2>Edit product tenant</h2><form method="post" class="form-grid">'.self::csrf().'<label>Organisation<input value="'.self::e($r['organisation']).'" readonly></label><label>Product<input value="'.self::e($r['product']).'" readonly></label><label>Display name<input name="display_name" value="'.self::e($r['display_name']).'" required></label><label>External reference<input name="external_ref" value="'.self::e($r['external_tenant_reference']).'"></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('SUSPENDED','SUSPENDED',$r['status']).self::option('REMOVED','REMOVED',$r['status']).'</select></label><div class="form-action"><button class="primary">Update tenant</button><a class="button" href="/admin/product-tenants">Cancel</a></div></form></section>';View::layout('Edit Product Tenant',$body);
    }
    private static function tenantStatus(string $guid,string $method): never
    {
        if($method!=='POST')Http::redirect('/admin/product-tenants');
        \Digiti\Application\Tenants\ManageTenant::status($guid,(string)($_POST['status']??'SUSPENDED'));
        self::redirect('/admin/product-tenants','Product tenant status updated.');
    }
    private static function tenantRemove(string $guid,string $method): never
    {
        if($method!=='POST')Http::redirect('/admin/product-tenants');
        \Digiti\Application\Tenants\ManageTenant::remove($guid);
        self::redirect('/admin/product-tenants','Product tenant removed; domains deactivated and history preserved.');
    }

    private static function domainEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT d.domain_guid,d.hostname,d.normalized_hostname,d.status,d.is_primary,d.domain_type,d.default_resource_type,d.verification_token,o.display_name organisation,p.name product,a.name application,pt.display_name tenant FROM domains d JOIN organisations o ON o.organisation_guid=d.organisation_guid JOIN products p ON p.product_guid=d.product_guid JOIN applications a ON a.application_guid=d.application_guid JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid WHERE d.domain_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Domain not found.');
        if($method==='POST'){
            \Digiti\Application\Routing\ManageDomain::edit($guid,$_POST);
            self::redirect('/admin/domains','Domain updated.');
        }
        $body='<section class="card"><h2>Edit domain</h2><form method="post" class="form-grid">'.self::csrf().'<label>Hostname<input value="'.self::e($r['hostname']).'" readonly><small>Hostname is immutable. Remove and add a new domain to change it.</small></label><label>Tenant<input value="'.self::e($r['tenant']).'" readonly></label><label>Application<input value="'.self::e($r['application']).'" readonly></label><label>Domain type<select name="domain_type">';foreach(['PLATFORM','PLATFORM_AUTH','SAAS_DEFAULT','CUSTOMER_CUSTOM','CUSTOMER_SUBDOMAIN']as$s)$body.=self::option($s,$s,$r['domain_type']);$body.='</select></label><label>Status<select name="status">';$statusOptions=[$r['status'],'SUSPENDED'];foreach(array_values(array_unique($statusOptions))as$s)$body.=self::option($s,$s,$r['status']);$body.='</select><small>DNS/TLS lifecycle states are managed by Verify/Activate actions.</small></label><label>Default resource type<input name="default_resource_type" value="'.self::e($r['default_resource_type']).'"></label><label class="check"><input type="checkbox" name="is_primary" value="1"'.($r['is_primary']?' checked':'').'> Primary domain</label><div class="form-action"><button class="primary">Update domain</button><a class="button" href="/admin/domains">Cancel</a></div></form><p class="muted">Verification token: <code>'.self::e($r['verification_token']).'</code></p></section>';View::layout('Edit Domain',$body,['subtitle'=>$r['hostname']]);
    }
    private static function domainRemove(string $guid,string $method): never
    {
        if($method!=='POST')Http::redirect('/admin/domains');
        \Digiti\Application\Routing\RemoveDomain::execute($guid);
        self::redirect('/admin/domains','Domain removed from active routing.');
    }

    private static function serviceAccountEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT service_account_guid,name,scopes,status,last_used_at FROM service_accounts WHERE service_account_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Service account not found.');
        if($method==='POST'){
            \Digiti\Application\Admin\RegistryMutations::serviceAccount('edit',$guid,$_POST);
            self::redirect('/admin/service-accounts','Service account updated.');
        }
        $scopes=json_decode($r['scopes']?:'[]',true);$body='<section class="card"><h2>Edit service account</h2><form method="post" class="form-grid">'.self::csrf().'<label>Name<input name="name" value="'.self::e($r['name']).'" required></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('REVOKED','REVOKED',$r['status']).self::option('REMOVED','REMOVED',$r['status']).'</select></label><label class="wide">Scopes<input name="scopes" value="'.self::e(implode(',',is_array($scopes)?$scopes:[])).'" required></label><div class="form-action"><button class="primary">Update service account</button><a class="button" href="/admin/service-accounts">Cancel</a></div></form><p class="muted">Last used: '.self::e($r['last_used_at']??'Never').'</p></section>';View::layout('Edit Service Account',$body);
    }
    private static function serviceAccountStatus(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/service-accounts');
        \Digiti\Application\Admin\RegistryMutations::serviceAccount('status',$guid,$_POST);
        self::redirect('/admin/service-accounts','Service account status updated.');
    }
    private static function serviceAccountRotate(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/service-accounts');
        $token=\Digiti\Application\Admin\RegistryMutations::serviceAccount('rotate',$guid,$_POST);
        self::redirect('/admin/service-accounts','NEW SERVICE TOKEN — copy now: '.$token);
    }
    private static function serviceAccountRemove(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/service-accounts');
        \Digiti\Application\Admin\RegistryMutations::serviceAccount('remove',$guid,$_POST);
        self::redirect('/admin/service-accounts','Service account removed/revoked.');
    }

    private static function featureNew(?string $unused,string $method): never
    {
        $products=Store::all('SELECT product_guid,name FROM products WHERE status="ACTIVE" ORDER BY name');
        if($method==='POST'){
            \Digiti\Application\Admin\RegistryMutations::feature('create',null,$_POST);
            self::redirect('/admin/commercial','Feature added.');
        }
        $body='<section class="card"><h2>Add feature</h2><form method="post" class="form-grid">'.self::csrf().'<label>Product'.self::selectRows('product_guid',$products,'product_guid','name',null,false).'</label><label>Feature key<input name="feature_key" placeholder="EPAPER.NEW_FEATURE" required></label><label>Name<input name="name" required></label><label>Type<select name="feature_type"><option>BOOLEAN</option><option>LIMIT</option><option>CONFIG</option></select></label><label>Unit<input name="unit" placeholder="count / GB / requests"></label><div class="form-action"><button class="primary">Add feature</button><a class="button" href="/admin/commercial">Cancel</a></div></form></section>';View::layout('Add Feature',$body);
    }
    private static function featureEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT f.feature_guid,f.feature_key,f.name,f.feature_type,f.unit,p.name product FROM features f LEFT JOIN products p ON p.product_guid=f.product_guid WHERE f.feature_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Feature not found.');if($method==='POST'){
            \Digiti\Application\Admin\RegistryMutations::feature('edit',$guid,$_POST);
            self::redirect('/admin/commercial','Feature updated.');
        }$body='<section class="card"><h2>Edit feature</h2><form method="post" class="form-grid">'.self::csrf().'<label>Feature key<input value="'.self::e($r['feature_key']).'" readonly></label><label>Product<input value="'.self::e($r['product']??'Platform').'" readonly></label><label>Name<input name="name" value="'.self::e($r['name']).'" required></label><label>Type<select name="feature_type">'.self::option('BOOLEAN','BOOLEAN',$r['feature_type']).self::option('LIMIT','LIMIT',$r['feature_type']).self::option('CONFIG','CONFIG',$r['feature_type']).'</select></label><label>Unit<input name="unit" value="'.self::e($r['unit']).'"></label><div class="form-action"><button class="primary">Update feature</button><a class="button" href="/admin/commercial">Cancel</a></div></form></section>';View::layout('Edit Feature',$body);
    }
    private static function featureRemove(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/commercial');
        \Digiti\Application\Admin\RegistryMutations::feature('remove',$guid,$_POST);
        self::redirect('/admin/commercial','Feature deleted.');
    }

    private static function planNew(?string $unused,string $method): never
    {
        $products=Store::all('SELECT product_guid,name FROM products WHERE status="ACTIVE" ORDER BY name');if($method==='POST'){
            \Digiti\Application\Admin\RegistryMutations::plan('create',null,$_POST);
            self::redirect('/admin/commercial','Plan added.');
        }$body='<section class="card"><h2>Add plan</h2><form method="post" class="form-grid">'.self::csrf().'<label>Product'.self::selectRows('product_guid',$products,'product_guid','name').'</label><label>Plan key<input name="plan_key" placeholder="BUSINESS" required></label><label>Name<input name="name" required></label><div class="form-action"><button class="primary">Add plan</button><a class="button" href="/admin/commercial">Cancel</a></div></form></section>';View::layout('Add Plan',$body);
    }
    private static function planEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT pl.plan_guid,pl.plan_key,pl.name,pl.status,p.name product FROM plans pl JOIN products p ON p.product_guid=pl.product_guid WHERE pl.plan_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Plan not found.');if($method==='POST'){
            \Digiti\Application\Admin\RegistryMutations::plan('edit',$guid,$_POST);
            self::redirect('/admin/commercial','Plan updated.');
        }$body='<section class="card"><h2>Edit plan</h2><form method="post" class="form-grid">'.self::csrf().'<label>Product<input value="'.self::e($r['product']).'" readonly></label><label>Plan key<input value="'.self::e($r['plan_key']).'" readonly></label><label>Name<input name="name" value="'.self::e($r['name']).'" required></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('SUSPENDED','SUSPENDED',$r['status']).self::option('REMOVED','REMOVED',$r['status']).'</select></label><div class="form-action"><button class="primary">Update plan</button><a class="button" href="/admin/commercial">Cancel</a></div></form></section>';View::layout('Edit Plan',$body);
    }
    private static function planStatus(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/commercial');
        \Digiti\Application\Admin\RegistryMutations::plan('status',$guid,$_POST);
        self::redirect('/admin/commercial','Plan status updated.');
    }
    private static function planRemove(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/commercial');
        \Digiti\Application\Admin\RegistryMutations::plan('remove',$guid,$_POST);
        self::redirect('/admin/commercial','Plan removed from new sales. Historical subscriptions remain intact.');
    }
    private static function priceNew(string $planGuid,string $method): never
    {
        $plan=Store::one('SELECT pl.plan_guid,pl.name,p.name product FROM plans pl JOIN products p ON p.product_guid=pl.product_guid WHERE pl.plan_guid=?',[self::guidBin($planGuid)]);if(!$plan)throw new \RuntimeException('Plan not found.');if($method==='POST'){
            \Digiti\Application\Admin\RegistryMutations::price('create',$planGuid,$_POST);
            self::redirect('/admin/commercial','New immutable price added.');
        }$body='<section class="card"><h2>Add price</h2><p>'.self::e($plan['product']).' — '.self::e($plan['name']).'</p><form method="post" class="form-grid">'.self::csrf().'<label>Currency<input name="currency" value="INR" maxlength="3" required></label><label>Amount<input name="amount" type="number" step="0.01" min="0" required></label><label>Interval<select name="billing_interval"><option>MONTH</option><option>YEAR</option><option>WEEK</option><option>DAY</option></select></label><label>Interval count<input name="interval_count" type="number" min="1" value="1" required></label><div class="form-action"><button class="primary">Add price</button><a class="button" href="/admin/commercial">Cancel</a></div></form><p class="muted">Prices are immutable after creation. Deactivate a price and add a new one instead of changing historical commercial terms.</p></section>';View::layout('Add Price',$body);
    }
    private static function priceStatus(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin/commercial');
        \Digiti\Application\Admin\RegistryMutations::price('status',$guid,$_POST);
        self::redirect('/admin/commercial','Price status updated.');
    }

    private static function subscriptionEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT s.subscription_guid,s.product_tenant_guid,s.status,s.billing_state,s.service_access_state,s.current_period_start,s.current_period_end,s.cancel_at_period_end,s.version,pt.display_name tenant,pl.name plan FROM subscriptions s JOIN product_tenants pt ON pt.product_tenant_guid=s.product_tenant_guid JOIN plans pl ON pl.plan_guid=s.plan_guid WHERE s.subscription_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Subscription not found.');if($method==='POST'){
            \Digiti\Application\Subscriptions\ManageSubscription::update($guid,$_POST);
            self::redirect('/admin/subscriptions','Subscription updated.');
        }
        $body='<section class="card"><h2>Edit subscription</h2><p>'.self::e($r['tenant']).' — '.self::e($r['plan']).'</p><form method="post" class="form-grid">'.self::csrf().'<input type="hidden" name="version" value="'.(int)$r['version'].'"><label>Status<select name="status">';foreach(['ACTIVE','PAST_DUE','GRACE_PERIOD','CANCELLED','EXPIRED']as$s)$body.=self::option($s,$s,$r['status']);$body.='</select></label><label>Billing state<select name="billing_state">';foreach(['ACTIVE','PAST_DUE','GRACE_PERIOD','EXPIRED','CANCELLED']as$s)$body.=self::option($s,$s,$r['billing_state']);$body.='</select></label><label>Service access<select name="service_access_state">';foreach(['FULL','RESTRICTED','BILLING_ONLY','BLOCKED']as$s)$body.=self::option($s,$s,$r['service_access_state']);$body.='</select></label><label>Period end<input type="datetime-local" name="current_period_end" value="'.self::e(str_replace(' ','T',substr($r['current_period_end'],0,16))).'" required></label><label class="check"><input type="checkbox" name="cancel_at_period_end" value="1"'.($r['cancel_at_period_end']?' checked':'').'> Cancel at period end</label><div class="form-action"><button class="primary">Update subscription</button><a class="button" href="/admin/subscriptions">Cancel</a></div></form></section>';View::layout('Edit Subscription',$body);
    }
    private static function invoiceVoid(string $guid,string $method): never
    {
        if($method!=='POST')Http::redirect('/admin/billing');
        \Digiti\Application\Billing\VoidInvoice::execute($guid);
        self::redirect('/admin/billing','Invoice voided. Financial record preserved.');
    }
}
