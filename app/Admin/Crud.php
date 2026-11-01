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
            '#^/admin/users/([0-9a-f-]{36})/membership-role$#' => 'userMembershipRole',
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

    private static function rejectArchiveStatus(array $input): void
    {
        if(($input['status']??'')==='REMOVED')
            throw new \InvalidArgumentException('Use the Archive action, not Edit, to remove a record and its dependent access.');
    }

    private static function ensureActivatable(string $entity,string $guid,string $newStatus): void
    {
        if($newStatus!=='ACTIVE')return;
        if($entity==='application') {
            $ok=Store::value('SELECT COUNT(*) FROM applications a JOIN products p ON p.product_guid=a.product_guid
                WHERE a.application_guid=? AND p.status="ACTIVE"',[self::guidBin($guid)]);
        } elseif($entity==='tenant') {
            $ok=Store::value('SELECT COUNT(*) FROM product_tenants pt
                JOIN products p ON p.product_guid=pt.product_guid
                JOIN organisations o ON o.organisation_guid=pt.organisation_guid
                WHERE pt.product_tenant_guid=? AND p.status="ACTIVE" AND o.status="ACTIVE"',[self::guidBin($guid)]);
        } elseif($entity==='plan') {
            $ok=Store::value('SELECT COUNT(*) FROM plans pl JOIN products p ON p.product_guid=pl.product_guid
                WHERE pl.plan_guid=? AND p.status="ACTIVE"',[self::guidBin($guid)]);
        } else return;
        if((int)$ok!==1)throw new \InvalidArgumentException('Activate the parent product and organisation before this record.');
    }

    public static function rowActions(string $entity,string $guid,string $status,string $base): string
    {
        return ActionUi::row($entity,$guid,$status,$base);
    }

    /**
     * Never revive an archived record using a generic Edit/Status endpoint.
     * Restoring requires the separate parent-aware recovery workflow.
     */
    private static function ensureNotRemoved(string $entity,string $guid): void
    {
        $sources=[
            'organisation'=>['organisations','organisation_guid'],
            'user'=>['platform_users','user_guid'],
            'product'=>['products','product_guid'],
            'application'=>['applications','application_guid'],
            'tenant'=>['product_tenants','product_tenant_guid'],
            'domain'=>['domains','domain_guid'],
            'service-account'=>['service_accounts','service_account_guid'],
            'plan'=>['plans','plan_guid'],
        ];
        if(!isset($sources[$entity])) throw new \InvalidArgumentException('Unsupported record type.');
        [$table,$key]=$sources[$entity];
        $status=Store::value("SELECT status FROM $table WHERE $key=?",[self::guidBin($guid)]);
        if($status===null)throw new \InvalidArgumentException('Record not found.');
        if($status==='REMOVED')throw new \InvalidArgumentException('This record is archived. Restore it before editing or activating it.');
    }

    private static function organisationEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT organisation_guid,organisation_code,legal_name,display_name,status FROM organisations WHERE organisation_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Organisation not found.');
        if($method==='POST'){self::ensureNotRemoved('organisation',$guid);self::rejectArchiveStatus($_POST);$code=strtoupper(preg_replace('/[^A-Z0-9_]+/','_',strtoupper(trim($_POST['code']??''))));$name=trim($_POST['display_name']??'');if(!$code||!$name)throw new \InvalidArgumentException('Code and display name are required.');$status=self::allowed($_POST['status']??'ACTIVE',['ACTIVE','SUSPENDED','REMOVED'],'ACTIVE');Store::exec('UPDATE organisations SET organisation_code=?,legal_name=?,display_name=?,status=?,updated_at=NOW(6) WHERE organisation_guid=?',[$code,trim($_POST['legal_name']??$name),$name,$status,self::guidBin($guid)]);self::invalidateDomainsBy('organisation_guid',$guid);Audit::log('organisation.update','organisation',$guid);self::redirect('/admin/organisations','Organisation updated.');}
        $body='<section class="card"><h2>Edit organisation</h2><form method="post" class="form-grid">'.self::csrf().'<label>Code<input name="code" value="'.self::e($r['organisation_code']).'" required></label><label>Display name<input name="display_name" value="'.self::e($r['display_name']).'" required></label><label>Legal name<input name="legal_name" value="'.self::e($r['legal_name']).'"></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('SUSPENDED','SUSPENDED',$r['status']).'</select></label><div class="form-action"><button class="primary">Update organisation</button><a class="button" href="/admin/organisations">Cancel</a></div></form></section>';View::layout('Edit Organisation',$body,['subtitle'=>$r['organisation_code']]);
    }
    private static function organisationStatus(string $guid,string $method): never { if($method!=='POST')Http::redirect('/admin/organisations');self::ensureNotRemoved('organisation',$guid);$status=self::allowed($_POST['status']??'SUSPENDED',['ACTIVE','SUSPENDED'],'SUSPENDED');Store::exec('UPDATE organisations SET status=?,updated_at=NOW(6) WHERE organisation_guid=?',[$status,self::guidBin($guid)]);self::invalidateDomainsBy('organisation_guid',$guid);Audit::log('organisation.status','organisation',$guid,'SUCCESS',['status'=>$status]);self::redirect('/admin/organisations','Organisation status updated.'); }
    private static function organisationRemove(string $guid,string $method): never
    {
        if($method!=='POST')Http::redirect('/admin/organisations');
        self::ensureNotRemoved('organisation',$guid);
        $bin=self::guidBin($guid);
        Store::tx(function()use($bin,$guid):void {
            Store::exec('UPDATE organisations SET status="REMOVED",updated_at=NOW(6) WHERE organisation_guid=?',[$bin]);
            Store::exec('UPDATE memberships SET status="REMOVED",updated_at=NOW(6) WHERE organisation_guid=?',[$bin]);
            Store::exec('UPDATE product_tenants SET status="REMOVED",updated_at=NOW(6) WHERE organisation_guid=?',[$bin]);
            Store::exec('UPDATE domains SET status="REMOVED",is_primary=0,updated_at=NOW(6) WHERE organisation_guid=?',[$bin]);
            Audit::log('organisation.remove','organisation',$guid);
        });
        self::invalidateDomainsBy('organisation_guid',$guid);
        self::redirect('/admin/organisations','Organisation archived. Related tenants, members and domains remain archived until separately reviewed.');
    }

    private static function userEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT user_guid,email,display_name,status,mfa_enabled FROM platform_users WHERE user_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('User not found.');
        if($method==='POST'){self::ensureNotRemoved('user',$guid);self::rejectArchiveStatus($_POST);$email=strtolower(trim($_POST['email']??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Valid email required.');$name=trim($_POST['display_name']??'');$status=self::allowed($_POST['status']??'ACTIVE',['ACTIVE','SUSPENDED'],'ACTIVE');$new=(string)($_POST['new_password']??'');if($new!==''&&strlen($new)<10)throw new \InvalidArgumentException('New password must be at least 10 characters.');Store::tx(function()use($email,$name,$status,$new,$guid):void {Store::exec('UPDATE platform_users SET email=?,display_name=?,status=?,updated_at=NOW(6) WHERE user_guid=?',[$email,$name,$status,self::guidBin($guid)]);if($new!=='')Store::exec('UPDATE platform_users SET password_hash=?,updated_at=NOW(6) WHERE user_guid=?',[password_hash($new,PASSWORD_DEFAULT),self::guidBin($guid)]);});Audit::log('user.update','platform_user',$guid);self::redirect('/admin/users','User updated.');}
        $memberships=Store::all('SELECT m.membership_guid,m.role_key,m.status,o.display_name organisation FROM memberships m JOIN organisations o ON o.organisation_guid=m.organisation_guid WHERE m.user_guid=? ORDER BY o.display_name',[self::guidBin($guid)]);$m='';
        foreach($memberships as $x){
            $m.='<tr><td>'.self::e($x['organisation']).'</td><td>'.self::e($x['role_key']).'</td><td>'.self::status($x['status']).'</td><td>';
            if($x['status']==='ACTIVE'){
                $opts='';
                foreach(['OWNER','ADMIN','MANAGER','VIEWER','WEBDESK','PUBLISHER_ADMIN','EDITOR','OPERATOR'] as $role)
                    $opts.=self::option($role,$role==='WEBDESK'?'WebDesk':$role,(string)$x['role_key']);
                $m.='<form method="post" action="/admin/users/'.self::e($guid).'/membership-role">'
                    .self::csrf().'<input type="hidden" name="membership_guid" value="'.self::e($x['membership_guid']).'">'
                    .'<label>Publisher role<select name="role_key" required>'.$opts.'</select></label>'
                    .'<button class="button" type="submit">Update role</button></form>';
            }else $m.='Inactive membership';
            $m.='</td></tr>';
        }
        if(!$m)$m='<tr><td colspan="4" class="muted">No memberships.</td></tr>';
        $body='<section class="card"><h2>Edit global user</h2><form method="post" class="form-grid">'.self::csrf().'<label>Name<input name="display_name" value="'.self::e($r['display_name']).'" required></label><label>Email<input type="email" name="email" value="'.self::e($r['email']).'" required></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('SUSPENDED','SUSPENDED',$r['status']).'</select></label><label>New password <span class="muted">optional</span><input type="password" name="new_password" minlength="10"></label><div class="form-action"><button class="primary">Update user</button><a class="button" href="/admin/users">Cancel</a></div></form></section><section class="card"><h2>Memberships</h2><div class="table-wrap"><table><thead><tr><th>Organisation</th><th>Role</th><th>Status</th><th>Role assignment</th></tr></thead><tbody>'.$m.'</tbody></table></div></section>';View::layout('Edit User',$body,['subtitle'=>$r['email']]);
    }
    private static function userMembershipRole(string $guid,string $method): never
    {
        if($method!=='POST')Http::redirect('/admin/users/'.$guid.'/edit');
        $admin=\Digiti\Core\Auth::requireAdmin();
        $role=Store::value('SELECT role_key FROM platform_admins WHERE admin_guid=? AND status="ACTIVE"',
            [self::guidBin((string)$admin['admin_guid'])]);
        if($role!=='SUPER_ADMIN')throw new \RuntimeException('Only a Platform super administrator can assign publisher roles.');
        $memberGuid=(string)($_POST['membership_guid']??'');
        $selected=strtoupper(trim((string)($_POST['role_key']??'')));
        $allowed=['OWNER','ADMIN','MANAGER','VIEWER','WEBDESK',
            'PUBLISHER_ADMIN','EDITOR','OPERATOR'];
        if(!in_array($selected,$allowed,true))throw new \InvalidArgumentException('Unknown publisher role');
        Store::tx(static function()use($guid,$memberGuid,$selected):void{
            $owner=Store::one('SELECT organisation_guid FROM memberships WHERE membership_guid=? AND user_guid=?',
                [self::guidBin($memberGuid),self::guidBin($guid)]);
            if(!$owner)throw new \InvalidArgumentException('Publisher membership not found');
            // Serialize role changes within an organisation so concurrent demotions
            // cannot silently remove its final OWNER/ADMIN.
            Store::one('SELECT organisation_guid FROM organisations WHERE organisation_guid=? FOR UPDATE',
                [self::guidBin((string)$owner['organisation_guid'])]);
            $record=Store::one('SELECT m.role_key,m.status,u.status user_status,o.status org_status '
                .'FROM memberships m JOIN platform_users u ON u.user_guid=m.user_guid '
                .'JOIN organisations o ON o.organisation_guid=m.organisation_guid '
                .'WHERE m.membership_guid=? AND m.user_guid=? FOR UPDATE',
                [self::guidBin($memberGuid),self::guidBin($guid)]);
            if(!$record||$record['status']!=='ACTIVE'||$record['user_status']!=='ACTIVE'
                ||$record['org_status']!=='ACTIVE')
                throw new \InvalidArgumentException('Publisher user and membership must be active');
            if($record['role_key']===$selected)return;
            if(in_array((string)$record['role_key'],['OWNER','ADMIN','PUBLISHER_ADMIN'],true)
                && !in_array($selected,['OWNER','ADMIN','PUBLISHER_ADMIN'],true)){
                $remaining=(int)Store::value('SELECT COUNT(*) FROM memberships m '
                    .'JOIN platform_users u ON u.user_guid=m.user_guid '
                    .'WHERE m.organisation_guid=? AND m.membership_guid<>? AND m.status="ACTIVE" '
                    .'AND u.status="ACTIVE" AND m.role_key IN ("OWNER","ADMIN","PUBLISHER_ADMIN")',
                    [self::guidBin((string)$owner['organisation_guid']),self::guidBin($memberGuid)]);
                if($remaining<1)throw new \InvalidArgumentException(
                    'Assign another active publisher owner/admin before converting the last owner to WebDesk.'
                );
            }
            Store::exec('UPDATE memberships SET role_key=?,updated_at=NOW(6) '
                .'WHERE membership_guid=? AND user_guid=? AND status="ACTIVE"',
                [$selected,self::guidBin($memberGuid),self::guidBin($guid)]);
            Audit::log('membership.role_changed','membership',$memberGuid,'SUCCESS',
                ['previous_role'=>$record['role_key'],'role'=>$selected,'publisher_user_guid'=>$guid]);
        });
        self::redirect('/admin/users/'.$guid.'/edit','Publisher role changed. Sign in again for updated access.');
    }

    private static function userStatus(string $guid,string $method): never { if($method!=='POST')Http::redirect('/admin/users');self::ensureNotRemoved('user',$guid);$status=self::allowed($_POST['status']??'SUSPENDED',['ACTIVE','SUSPENDED'],'SUSPENDED');Store::exec('UPDATE platform_users SET status=?,updated_at=NOW(6) WHERE user_guid=?',[$status,self::guidBin($guid)]);Audit::log('user.status','platform_user',$guid);self::redirect('/admin/users','User status updated.'); }
    private static function userRemove(string $guid,string $method): never
    {
        if($method!=='POST')Http::redirect('/admin/users');
        self::ensureNotRemoved('user',$guid);
        $bin=self::guidBin($guid);
        Store::tx(function()use($bin,$guid):void {
            Store::exec('UPDATE platform_users SET status="REMOVED",updated_at=NOW(6) WHERE user_guid=?',[$bin]);
            Store::exec('UPDATE memberships SET status="REMOVED",updated_at=NOW(6) WHERE user_guid=?',[$bin]);
            Audit::log('user.remove','platform_user',$guid);
        });
        self::redirect('/admin/users','User archived; memberships were disabled and will not be silently restored.');
    }

    private static function productEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT product_guid,product_key,name,status,description FROM products WHERE product_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Product not found.');
        if($method==='POST'){self::ensureNotRemoved('product',$guid);self::rejectArchiveStatus($_POST);$name=trim($_POST['name']??'');if(!$name)throw new \InvalidArgumentException('Product name is required.');$status=self::allowed($_POST['status']??'ACTIVE',['ACTIVE','SUSPENDED','REMOVED'],'ACTIVE');Store::exec('UPDATE products SET name=?,description=?,status=?,updated_at=NOW(6) WHERE product_guid=?',[$name,trim($_POST['description']??''),$status,self::guidBin($guid)]);self::invalidateDomainsBy('product_guid',$guid);Audit::log('product.update','product',$guid,'SUCCESS',['name'=>$name,'status'=>$status]);self::redirect('/admin/products','Product updated.');}
        $body='<section class="card"><h2>Edit product</h2><form method="post" class="form-grid">'.self::csrf().'<label>Product key<input value="'.self::e($r['product_key']).'" readonly><small>Stable integration key; create a new product instead of changing this key.</small></label><label>Name<input name="name" value="'.self::e($r['name']).'" required></label><label class="wide">Description<textarea name="description" rows="4">'.self::e($r['description']).'</textarea></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('SUSPENDED','SUSPENDED',$r['status']).'</select></label><div class="form-action"><button class="primary">Update product</button><a class="button" href="/admin/products">Cancel</a></div></form></section>';View::layout('Edit Product',$body,['subtitle'=>$r['product_key']]);
    }
    private static function productStatus(string $guid,string $method): never { if($method!=='POST')Http::redirect('/admin/products');self::ensureNotRemoved('product',$guid);$status=self::allowed($_POST['status']??'SUSPENDED',['ACTIVE','SUSPENDED'],'SUSPENDED');Store::exec('UPDATE products SET status=?,updated_at=NOW(6) WHERE product_guid=?',[$status,self::guidBin($guid)]);self::invalidateDomainsBy('product_guid',$guid);Audit::log('product.status','product',$guid);self::redirect('/admin/products','Product status updated.'); }
    private static function productRemove(string $guid,string $method): never
    {
        if($method!=='POST')Http::redirect('/admin/products');
        self::ensureNotRemoved('product',$guid);
        $bin=self::guidBin($guid);
        Store::tx(function()use($bin,$guid):void {
            Store::exec('UPDATE products SET status="REMOVED",updated_at=NOW(6) WHERE product_guid=?',[$bin]);
            Store::exec('UPDATE applications SET status="REMOVED",updated_at=NOW(6) WHERE product_guid=?',[$bin]);
            Store::exec('UPDATE product_tenants SET status="REMOVED",updated_at=NOW(6) WHERE product_guid=?',[$bin]);
            Store::exec('UPDATE domains SET status="REMOVED",is_primary=0,updated_at=NOW(6) WHERE product_guid=?',[$bin]);
            Store::exec('UPDATE service_accounts SET status="REMOVED" WHERE product_guid=?',[$bin]);
            Audit::log('product.remove','product',$guid);
        });
        self::invalidateDomainsBy('product_guid',$guid);
        self::redirect('/admin/products','Product archived. Dependent application, tenants and domains require separate recovery.');
    }

    private static function applicationEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT application_guid,product_guid,application_key,name,base_url,admin_url,health_url,callback_url,oauth_client_id,integration_mode,status,current_version FROM applications WHERE application_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Application not found.');
        if($method==='POST'){self::ensureNotRemoved('application',$guid);self::rejectArchiveStatus($_POST);$base=rtrim(trim($_POST['base_url']??''),'/');if(!filter_var($base,FILTER_VALIDATE_URL))throw new \InvalidArgumentException('Valid base URL required.');$callback=trim($_POST['callback_url']??'')?:null;$status=self::allowed($_POST['status']??'ACTIVE',['ACTIVE','SUSPENDED','REMOVED'],'ACTIVE');self::ensureActivatable('application',$guid,$status);Store::exec('UPDATE applications SET name=?,base_url=?,admin_url=?,health_url=?,callback_url=?,integration_mode=?,status=?,updated_at=NOW(6) WHERE application_guid=?',[trim($_POST['name']??''),$base,trim($_POST['admin_url']??'')?:null,trim($_POST['health_url']??'')?:null,$callback,self::allowed($_POST['integration_mode']??'REMOTE_API',['REMOTE_API','LOCAL_MODULE','INTERNAL_API'],'REMOTE_API'),$status,self::guidBin($guid)]);if($callback)Store::exec('INSERT INTO application_redirect_uris(redirect_guid,application_guid,redirect_uri,status,created_at) VALUES(?,?,?,"ACTIVE",NOW(6)) ON DUPLICATE KEY UPDATE status="ACTIVE"',[self::guidBin(Uuid::v7()),self::guidBin($guid),$callback]);self::invalidateDomainsBy('application_guid',$guid);Audit::log('application.update','application',$guid);self::redirect('/admin/applications','Application updated.');}
        $body='<section class="card"><h2>Edit application</h2><form method="post" class="form-grid">'.self::csrf().'<label>Application key<input value="'.self::e($r['application_key']).'" readonly></label><label>Name<input name="name" value="'.self::e($r['name']).'" required></label><label>Base URL<input name="base_url" type="url" value="'.self::e($r['base_url']).'" required></label><label>Admin URL<input name="admin_url" type="url" value="'.self::e($r['admin_url']).'"></label><label>Health URL<input name="health_url" type="url" value="'.self::e($r['health_url']).'"></label><label>OAuth callback<input name="callback_url" type="url" value="'.self::e($r['callback_url']).'"></label><label>Integration mode<select name="integration_mode">'.self::option('REMOTE_API','REMOTE_API',$r['integration_mode']).self::option('INTERNAL_API','INTERNAL_API',$r['integration_mode']).self::option('LOCAL_MODULE','LOCAL_MODULE',$r['integration_mode']).'</select></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('SUSPENDED','SUSPENDED',$r['status']).'</select></label><div class="form-action"><button class="primary">Update application</button><a class="button" href="/admin/applications">Cancel</a></div></form><p class="muted">OAuth Client ID: <code>'.self::e($r['oauth_client_id']).'</code> · Current version: '.self::e($r['current_version']??'—').'</p></section>';View::layout('Edit Application',$body,['subtitle'=>$r['application_key']]);
    }
    private static function applicationStatus(string $guid,string $method): never { if($method!=='POST')Http::redirect('/admin/applications');self::ensureNotRemoved('application',$guid);$status=self::allowed($_POST['status']??'SUSPENDED',['ACTIVE','SUSPENDED'],'SUSPENDED');self::ensureActivatable('application',$guid,$status);Store::exec('UPDATE applications SET status=?,updated_at=NOW(6) WHERE application_guid=?',[$status,self::guidBin($guid)]);self::invalidateDomainsBy('application_guid',$guid);Audit::log('application.status','application',$guid);self::redirect('/admin/applications','Application status updated.'); }
    private static function applicationRemove(string $guid,string $method): never
    {
        if($method!=='POST')Http::redirect('/admin/applications');
        self::ensureNotRemoved('application',$guid);
        $bin=self::guidBin($guid);
        Store::tx(function()use($bin,$guid):void {
            Store::exec('UPDATE applications SET status="REMOVED",updated_at=NOW(6) WHERE application_guid=?',[$bin]);
            Store::exec('UPDATE application_redirect_uris SET status="REMOVED" WHERE application_guid=?',[$bin]);
            Store::exec('UPDATE domains SET status="SUSPENDED",is_primary=0,updated_at=NOW(6) WHERE application_guid=? AND status<>"REMOVED"',[$bin]);
            Audit::log('application.remove','application',$guid);
        });
        self::invalidateDomainsBy('application_guid',$guid);
        self::redirect('/admin/applications','Application archived. Its domains remain suspended pending revalidation.');
    }

    private static function tenantEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT pt.product_tenant_guid,pt.display_name,pt.external_tenant_reference,pt.status,o.display_name organisation,p.name product FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid JOIN products p ON p.product_guid=pt.product_guid WHERE pt.product_tenant_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Product tenant not found.');
        if($method==='POST'){self::ensureNotRemoved('tenant',$guid);self::rejectArchiveStatus($_POST);$status=self::allowed($_POST['status']??'ACTIVE',['ACTIVE','SUSPENDED','REMOVED'],'ACTIVE');self::ensureActivatable('tenant',$guid,$status);Store::exec('UPDATE product_tenants SET display_name=?,external_tenant_reference=?,status=?,updated_at=NOW(6) WHERE product_tenant_guid=?',[trim($_POST['display_name']??''),trim($_POST['external_ref']??'')?:null,$status,self::guidBin($guid)]);RedisStore::del('entitlements:'.$guid);self::invalidateDomainsBy('product_tenant_guid',$guid);Audit::log('product_tenant.update','product_tenant',$guid);self::redirect('/admin/product-tenants','Product tenant updated.');}
        $body='<section class="card"><h2>Edit product tenant</h2><form method="post" class="form-grid">'.self::csrf().'<label>Organisation<input value="'.self::e($r['organisation']).'" readonly></label><label>Product<input value="'.self::e($r['product']).'" readonly></label><label>Display name<input name="display_name" value="'.self::e($r['display_name']).'" required></label><label>External reference<input name="external_ref" value="'.self::e($r['external_tenant_reference']).'"></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('SUSPENDED','SUSPENDED',$r['status']).'</select></label><div class="form-action"><button class="primary">Update tenant</button><a class="button" href="/admin/product-tenants">Cancel</a></div></form></section>';View::layout('Edit Product Tenant',$body);
    }
    private static function tenantStatus(string $guid,string $method): never { if($method!=='POST')Http::redirect('/admin/product-tenants');self::ensureNotRemoved('tenant',$guid);$status=self::allowed($_POST['status']??'SUSPENDED',['ACTIVE','SUSPENDED'],'SUSPENDED');self::ensureActivatable('tenant',$guid,$status);Store::exec('UPDATE product_tenants SET status=?,updated_at=NOW(6) WHERE product_tenant_guid=?',[$status,self::guidBin($guid)]);RedisStore::del('entitlements:'.$guid);self::invalidateDomainsBy('product_tenant_guid',$guid);Audit::log('product_tenant.status','product_tenant',$guid);self::redirect('/admin/product-tenants','Product tenant status updated.'); }
    private static function tenantRemove(string $guid,string $method): never
    {
        if($method!=='POST')Http::redirect('/admin/product-tenants');
        self::ensureNotRemoved('tenant',$guid);
        $bin=self::guidBin($guid);
        $domains=Store::all('SELECT hostname FROM domains WHERE product_tenant_guid=?',[$bin]);
        Store::tx(function()use($bin,$guid):void {
            Store::exec('UPDATE product_tenants SET status="REMOVED",updated_at=NOW(6) WHERE product_tenant_guid=?',[$bin]);
            Store::exec('UPDATE domains SET status="REMOVED",updated_at=NOW(6) WHERE product_tenant_guid=?',[$bin]);
            Audit::log('product_tenant.remove','product_tenant',$guid);
        });
        foreach($domains as $d)RedisStore::del('domain:'.$d['hostname']);
        RedisStore::del('entitlements:'.$guid);
        self::redirect('/admin/product-tenants','Publisher tenant archived. Domain recovery must be reviewed individually.');
    }

    private static function domainEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT d.domain_guid,d.hostname,d.normalized_hostname,d.status,d.is_primary,d.domain_type,d.default_resource_type,d.verification_token,o.display_name organisation,p.name product,a.name application,pt.display_name tenant FROM domains d JOIN organisations o ON o.organisation_guid=d.organisation_guid JOIN products p ON p.product_guid=d.product_guid JOIN applications a ON a.application_guid=d.application_guid JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid WHERE d.domain_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Domain not found.');
        if($method==='POST'){self::ensureNotRemoved('domain',$guid);self::rejectArchiveStatus($_POST);$allowedStatus=[$r['status'],'SUSPENDED'];/* Reactivation requires onboarding go-live review. */$status=self::allowed($_POST['status']??$r['status'],array_values(array_unique($allowedStatus)),$r['status']);Store::exec('UPDATE domains SET is_primary=?,domain_type=?,status=?,default_resource_type=?,updated_at=NOW(6) WHERE domain_guid=?',[!empty($_POST['is_primary'])?1:0,self::allowed($_POST['domain_type']??'CUSTOMER_CUSTOM',['PLATFORM','PLATFORM_AUTH','SAAS_DEFAULT','CUSTOMER_CUSTOM','CUSTOMER_SUBDOMAIN'],'CUSTOMER_CUSTOM'),$status,trim($_POST['default_resource_type']??'')?:null,self::guidBin($guid)]);RedisStore::del('domain:'.$r['hostname']);Audit::log('domain.update','domain',$guid);self::redirect('/admin/domains','Domain updated.');}
        // Publisher and Application selectors are a separate protected, audited form.
        // Do not mix their mutation with general domain metadata/status updates.
        $mapping=DomainMapping::panel($r);
        $body='<details class="card platform-form-disclosure" id="advanced-domain-settings"><summary>Advanced domain settings</summary>'
            .'<div class="platform-form-disclosure-body"><p class="muted">Hostname and domain routing are managed from the publisher onboarding tracker. Changing technical fields here does not change the newspaper account.</p>'
            .'<form method="post" class="form-grid">'.self::csrf()
            .'<label>Website address<input value="'.self::e($r['hostname']).'" readonly></label>'
            .'<label>Domain type<select name="domain_type">';
        $typeOptions=in_array($r['domain_type'],['CUSTOMER_CUSTOM','CUSTOMER_SUBDOMAIN'],true)
            ? ['CUSTOMER_CUSTOM','CUSTOMER_SUBDOMAIN']
            : [$r['domain_type']];
        foreach($typeOptions as $value) $body.=self::option($value,$value,$r['domain_type']);
        $body.='</select></label><label>Status<select name="status">';
        // Do not offer direct reactivation: only onboarding can approve go-live.
        $statusOptions=array_values(array_unique([$r['status'],'SUSPENDED']));
        foreach($statusOptions as $value) $body.=self::option($value,$value,$r['status']);
        $body.='</select><small>Reactivation and readiness checks are managed in Publisher Onboarding.</small></label>'
            .'<label>Default resource type<input name="default_resource_type" value="'.self::e($r['default_resource_type']).'"></label>'
            .'<label class="check"><input type="checkbox" name="is_primary" value="1"'.($r['is_primary']?' checked':'').'> Primary domain</label>'
            .'<div class="form-action"><button type="submit">Save advanced settings</button>'
            .'<a class="button" href="/admin/domains">Cancel</a></div></form>'
            .'<p class="muted">Verification token: <code>'.self::e($r['verification_token']).'</code></p>'
            .'</div></details>';
        View::layout('Connect Publisher & Application',$mapping.$body,['subtitle'=>$r['hostname']]);
    }
    private static function domainRemove(string $guid,string $method): never { if($method!=='POST')Http::redirect('/admin/domains');$r=Store::one('SELECT d.hostname,d.domain_type,a.base_url FROM domains d JOIN applications a ON a.application_guid=d.application_guid WHERE d.domain_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Domain not found.');if(!in_array($r['domain_type'],['CUSTOMER_CUSTOM','CUSTOMER_SUBDOMAIN'],true) || strtolower((string)(parse_url((string)$r['base_url'],PHP_URL_HOST)?:''))===strtolower((string)$r['hostname']))throw new \InvalidArgumentException('Canonical or system domains cannot be archived here.');Store::exec('UPDATE domains SET status="REMOVED",is_primary=0,updated_at=NOW(6) WHERE domain_guid=?',[self::guidBin($guid)]);RedisStore::del('domain:'.$r['hostname']);Audit::log('domain.remove','domain',$guid);self::redirect('/admin/domains','Domain removed from active routing.'); }

    private static function serviceAccountEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT service_account_guid,name,scopes,status,last_used_at FROM service_accounts WHERE service_account_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Service account not found.');
        if($method==='POST'){self::ensureNotRemoved('service-account',$guid);self::rejectArchiveStatus($_POST);$scopes=array_values(array_filter(array_map('trim',explode(',',$_POST['scopes']??''))));$status=self::allowed($_POST['status']??'ACTIVE',['ACTIVE','REVOKED','REMOVED'],'ACTIVE');if($r['status']!=='ACTIVE' && $status==='ACTIVE')throw new \InvalidArgumentException('Rotate a revoked service token instead of reactivating its previous secret.');Store::exec('UPDATE service_accounts SET name=?,scopes=?,status=? WHERE service_account_guid=?',[trim($_POST['name']??''),json_encode($scopes),$status,self::guidBin($guid)]);Audit::log('service_account.update','service_account',$guid);self::redirect('/admin/service-accounts','Service account updated.');}
        $scopes=json_decode($r['scopes']?:'[]',true);$body='<section class="card"><h2>Edit service account</h2><form method="post" class="form-grid">'.self::csrf().'<label>Name<input name="name" value="'.self::e($r['name']).'" required></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('REVOKED','REVOKED',$r['status']).'</select></label><label class="wide">Scopes<input name="scopes" value="'.self::e(implode(',',is_array($scopes)?$scopes:[])).'" required></label><div class="form-action"><button class="primary">Update service account</button><a class="button" href="/admin/service-accounts">Cancel</a></div></form><p class="muted">Last used: '.self::e($r['last_used_at']??'Never').'</p></section>';View::layout('Edit Service Account',$body);
    }
    private static function serviceAccountStatus(string $guid,string $method): never { if($method!=='POST')Http::redirect('/admin/service-accounts');self::ensureNotRemoved('service-account',$guid);$status=self::allowed($_POST['status']??'REVOKED',['REVOKED'],'REVOKED');Store::exec('UPDATE service_accounts SET status=? WHERE service_account_guid=?',[$status,self::guidBin($guid)]);Audit::log('service_account.status','service_account',$guid);self::redirect('/admin/service-accounts','Service account status updated.'); }
    private static function serviceAccountRotate(string $guid,string $method): never { if($method!=='POST')Http::redirect('/admin/service-accounts');self::ensureNotRemoved('service-account',$guid);$token='dgt_'.rtrim(strtr(base64_encode(random_bytes(36)),'+/','-_'),'=');Store::exec('UPDATE service_accounts SET token_hash=?,status="ACTIVE",rotated_at=NOW(6) WHERE service_account_guid=?',[hash('sha256',$token,true),self::guidBin($guid)]);Audit::log('service_account.rotate','service_account',$guid);self::redirect('/admin/service-accounts','NEW SERVICE TOKEN — copy now: '.$token); }
    private static function serviceAccountRemove(string $guid,string $method): never { if($method!=='POST')Http::redirect('/admin/service-accounts');Store::exec('UPDATE service_accounts SET status="REMOVED" WHERE service_account_guid=?',[self::guidBin($guid)]);Audit::log('service_account.remove','service_account',$guid);self::redirect('/admin/service-accounts','Service account removed/revoked.'); }

    private static function featureNew(?string $unused,string $method): never
    {
        $products=Store::all('SELECT product_guid,name FROM products WHERE status="ACTIVE" ORDER BY name');
        if($method==='POST'){$key=strtoupper(trim($_POST['feature_key']??''));if(!preg_match('/^[A-Z0-9_.-]{3,128}$/',$key))throw new \InvalidArgumentException('Feature key format is invalid.');$guid=Uuid::v7();Store::exec('INSERT INTO features(feature_guid,product_guid,feature_key,name,feature_type,unit,created_at) VALUES(?,?,?,?,?,?,NOW(6))',[self::guidBin($guid),!empty($_POST['product_guid'])?self::guidBin($_POST['product_guid']):null,$key,trim($_POST['name']??''),self::allowed($_POST['feature_type']??'BOOLEAN',['BOOLEAN','LIMIT','CONFIG'],'BOOLEAN'),trim($_POST['unit']??'')?:null]);Audit::log('feature.create','feature',$guid);self::redirect('/admin/commercial','Feature added.');}
        $body='<section class="card"><h2>Add feature</h2><form method="post" class="form-grid">'.self::csrf().'<label>Product'.self::selectRows('product_guid',$products,'product_guid','name',null,false).'</label><label>Feature key<input name="feature_key" placeholder="EPAPER.NEW_FEATURE" required></label><label>Name<input name="name" required></label><label>Type<select name="feature_type"><option>BOOLEAN</option><option>LIMIT</option><option>CONFIG</option></select></label><label>Unit<input name="unit" placeholder="count / GB / requests"></label><div class="form-action"><button class="primary">Add feature</button><a class="button" href="/admin/commercial">Cancel</a></div></form></section>';View::layout('Add Feature',$body);
    }
    private static function featureEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT f.feature_guid,f.feature_key,f.name,f.feature_type,f.unit,p.name product FROM features f LEFT JOIN products p ON p.product_guid=f.product_guid WHERE f.feature_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Feature not found.');if($method==='POST'){Store::exec('UPDATE features SET name=?,feature_type=?,unit=? WHERE feature_guid=?',[trim($_POST['name']??''),self::allowed($_POST['feature_type']??'BOOLEAN',['BOOLEAN','LIMIT','CONFIG'],'BOOLEAN'),trim($_POST['unit']??'')?:null,self::guidBin($guid)]);Audit::log('feature.update','feature',$guid);self::redirect('/admin/commercial','Feature updated.');}$body='<section class="card"><h2>Edit feature</h2><form method="post" class="form-grid">'.self::csrf().'<label>Feature key<input value="'.self::e($r['feature_key']).'" readonly></label><label>Product<input value="'.self::e($r['product']??'Platform').'" readonly></label><label>Name<input name="name" value="'.self::e($r['name']).'" required></label><label>Type<select name="feature_type">'.self::option('BOOLEAN','BOOLEAN',$r['feature_type']).self::option('LIMIT','LIMIT',$r['feature_type']).self::option('CONFIG','CONFIG',$r['feature_type']).'</select></label><label>Unit<input name="unit" value="'.self::e($r['unit']).'"></label><div class="form-action"><button class="primary">Update feature</button><a class="button" href="/admin/commercial">Cancel</a></div></form></section>';View::layout('Edit Feature',$body);
    }
    private static function featureRemove(string $guid,string $method): never { if($method!=='POST')Http::redirect('/admin/commercial');$deps=(int)Store::value('SELECT (SELECT COUNT(*) FROM plan_features WHERE feature_guid=?)+(SELECT COUNT(*) FROM entitlement_grants WHERE feature_guid=?)+(SELECT COUNT(*) FROM usage_meters WHERE feature_guid=?)',[self::guidBin($guid),self::guidBin($guid),self::guidBin($guid)]);if($deps>0)throw new \RuntimeException('Feature is already referenced by plans, grants or usage and cannot be deleted. Remove those references first.');Store::exec('DELETE FROM features WHERE feature_guid=?',[self::guidBin($guid)]);Audit::log('feature.remove','feature',$guid);self::redirect('/admin/commercial','Feature deleted.'); }

    private static function planNew(?string $unused,string $method): never
    {
        $products=Store::all('SELECT product_guid,name FROM products WHERE status="ACTIVE" ORDER BY name');if($method==='POST'){$key=strtoupper(preg_replace('/[^A-Z0-9_]+/','_',trim($_POST['plan_key']??'')));$guid=Uuid::v7();Store::exec('INSERT INTO plans(plan_guid,product_guid,plan_key,name,status,created_at,updated_at) VALUES(?,?,?, ?,"ACTIVE",NOW(6),NOW(6))',[self::guidBin($guid),self::guidBin($_POST['product_guid']),$key,trim($_POST['name']??'')]);Audit::log('plan.create','plan',$guid);self::redirect('/admin/commercial','Plan added.');}$body='<section class="card"><h2>Add plan</h2><form method="post" class="form-grid">'.self::csrf().'<label>Product'.self::selectRows('product_guid',$products,'product_guid','name').'</label><label>Plan key<input name="plan_key" placeholder="BUSINESS" required></label><label>Name<input name="name" required></label><div class="form-action"><button class="primary">Add plan</button><a class="button" href="/admin/commercial">Cancel</a></div></form></section>';View::layout('Add Plan',$body);
    }
    private static function planEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT pl.plan_guid,pl.plan_key,pl.name,pl.status,p.name product FROM plans pl JOIN products p ON p.product_guid=pl.product_guid WHERE pl.plan_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Plan not found.');if($method==='POST'){self::ensureNotRemoved('plan',$guid);self::rejectArchiveStatus($_POST);$status=self::allowed($_POST['status']??'ACTIVE',['ACTIVE','SUSPENDED','REMOVED'],'ACTIVE');self::ensureActivatable('plan',$guid,$status);Store::exec('UPDATE plans SET name=?,status=?,updated_at=NOW(6) WHERE plan_guid=?',[trim($_POST['name']??''),$status,self::guidBin($guid)]);Audit::log('plan.update','plan',$guid);self::redirect('/admin/commercial','Plan updated.');}$body='<section class="card"><h2>Edit plan</h2><form method="post" class="form-grid">'.self::csrf().'<label>Product<input value="'.self::e($r['product']).'" readonly></label><label>Plan key<input value="'.self::e($r['plan_key']).'" readonly></label><label>Name<input name="name" value="'.self::e($r['name']).'" required></label><label>Status<select name="status">'.self::option('ACTIVE','ACTIVE',$r['status']).self::option('SUSPENDED','SUSPENDED',$r['status']).'</select></label><div class="form-action"><button class="primary">Update plan</button><a class="button" href="/admin/commercial">Cancel</a></div></form></section>';View::layout('Edit Plan',$body);
    }
    private static function planStatus(string $guid,string $method): never { if($method!=='POST')Http::redirect('/admin/commercial');self::ensureNotRemoved('plan',$guid);$status=self::allowed($_POST['status']??'SUSPENDED',['ACTIVE','SUSPENDED'],'SUSPENDED');self::ensureActivatable('plan',$guid,$status);Store::exec('UPDATE plans SET status=?,updated_at=NOW(6) WHERE plan_guid=?',[$status,self::guidBin($guid)]);Audit::log('plan.status','plan',$guid);self::redirect('/admin/commercial','Plan status updated.'); }
    private static function planRemove(string $guid,string $method): never
    {
        if($method!=='POST')Http::redirect('/admin/commercial');
        self::ensureNotRemoved('plan',$guid);
        $bin=self::guidBin($guid);
        Store::tx(function()use($bin,$guid):void {
            Store::exec('UPDATE plans SET status="REMOVED",updated_at=NOW(6) WHERE plan_guid=?',[$bin]);
            Store::exec('UPDATE prices SET status="REMOVED" WHERE plan_guid=?',[$bin]);
            Audit::log('plan.remove','plan',$guid);
        });
        self::redirect('/admin/commercial','Plan archived. Historical subscriptions remain intact.');
    }

    private static function priceNew(string $planGuid,string $method): never
    {
        $plan=Store::one('SELECT pl.plan_guid,pl.name,p.name product FROM plans pl JOIN products p ON p.product_guid=pl.product_guid WHERE pl.plan_guid=?',[self::guidBin($planGuid)]);if(!$plan)throw new \RuntimeException('Plan not found.');if($method==='POST'){$amount=(int)round(((float)($_POST['amount']??0))*100);if($amount<0)throw new \InvalidArgumentException('Price must be zero or greater.');$guid=Uuid::v7();Store::exec('INSERT INTO prices(price_guid,plan_guid,currency,amount_minor,billing_interval,interval_count,status,created_at) VALUES(?,?,?,?,?, ?,"ACTIVE",NOW(6))',[self::guidBin($guid),self::guidBin($planGuid),strtoupper(trim($_POST['currency']??'INR')),$amount,self::allowed($_POST['billing_interval']??'MONTH',['MONTH','YEAR','WEEK','DAY'],'MONTH'),max(1,(int)($_POST['interval_count']??1))]);Audit::log('price.create','price',$guid);self::redirect('/admin/commercial','New immutable price added.');}$body='<section class="card"><h2>Add price</h2><p>'.self::e($plan['product']).' — '.self::e($plan['name']).'</p><form method="post" class="form-grid">'.self::csrf().'<label>Currency<input name="currency" value="INR" maxlength="3" required></label><label>Amount<input name="amount" type="number" step="0.01" min="0" required></label><label>Interval<select name="billing_interval"><option>MONTH</option><option>YEAR</option><option>WEEK</option><option>DAY</option></select></label><label>Interval count<input name="interval_count" type="number" min="1" value="1" required></label><div class="form-action"><button class="primary">Add price</button><a class="button" href="/admin/commercial">Cancel</a></div></form><p class="muted">Prices are immutable after creation. Deactivate a price and add a new one instead of changing historical commercial terms.</p></section>';View::layout('Add Price',$body);
    }
    private static function priceStatus(string $guid,string $method): never { if($method!=='POST')Http::redirect('/admin/commercial');$status=self::allowed($_POST['status']??'SUSPENDED',['ACTIVE','SUSPENDED','REMOVED'],'SUSPENDED');Store::exec('UPDATE prices SET status=? WHERE price_guid=?',[$status,self::guidBin($guid)]);Audit::log('price.status','price',$guid);self::redirect('/admin/commercial','Price status updated.'); }

    private static function subscriptionEdit(string $guid,string $method): never
    {
        $r=Store::one('SELECT s.subscription_guid,s.product_tenant_guid,s.status,s.billing_state,s.service_access_state,s.current_period_start,s.current_period_end,s.cancel_at_period_end,s.version,pt.display_name tenant,pl.name plan FROM subscriptions s JOIN product_tenants pt ON pt.product_tenant_guid=s.product_tenant_guid JOIN plans pl ON pl.plan_guid=s.plan_guid WHERE s.subscription_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Subscription not found.');if($method==='POST'){$status=self::allowed($_POST['status']??$r['status'],['ACTIVE','PAST_DUE','GRACE_PERIOD','CANCELLED','EXPIRED'],$r['status']);$billing=self::allowed($_POST['billing_state']??$r['billing_state'],['ACTIVE','PAST_DUE','GRACE_PERIOD','EXPIRED','CANCELLED'],$r['billing_state']);$access=self::allowed($_POST['service_access_state']??$r['service_access_state'],['FULL','RESTRICTED','BILLING_ONLY','BLOCKED'],$r['service_access_state']);$end=trim($_POST['current_period_end']??'');if(!$end)throw new \InvalidArgumentException('Period end required.');Store::exec('UPDATE subscriptions SET status=?,billing_state=?,service_access_state=?,current_period_end=?,cancel_at_period_end=?,version=version+1,updated_at=NOW(6) WHERE subscription_guid=?',[$status,$billing,$access,$end,!empty($_POST['cancel_at_period_end'])?1:0,self::guidBin($guid)]);RedisStore::del('entitlements:'.$r['product_tenant_guid']);Audit::log('subscription.update','subscription',$guid);self::redirect('/admin/subscriptions','Subscription updated.');}$body='<section class="card"><h2>Edit subscription</h2><p>'.self::e($r['tenant']).' — '.self::e($r['plan']).'</p><form method="post" class="form-grid">'.self::csrf().'<label>Status<select name="status">';foreach(['ACTIVE','PAST_DUE','GRACE_PERIOD','CANCELLED','EXPIRED']as$s)$body.=self::option($s,$s,$r['status']);$body.='</select></label><label>Billing state<select name="billing_state">';foreach(['ACTIVE','PAST_DUE','GRACE_PERIOD','EXPIRED','CANCELLED']as$s)$body.=self::option($s,$s,$r['billing_state']);$body.='</select></label><label>Service access<select name="service_access_state">';foreach(['FULL','RESTRICTED','BILLING_ONLY','BLOCKED']as$s)$body.=self::option($s,$s,$r['service_access_state']);$body.='</select></label><label>Period end<input type="datetime-local" name="current_period_end" value="'.self::e(str_replace(' ','T',substr($r['current_period_end'],0,16))).'" required></label><label class="check"><input type="checkbox" name="cancel_at_period_end" value="1"'.($r['cancel_at_period_end']?' checked':'').'> Cancel at period end</label><div class="form-action"><button class="primary">Update subscription</button><a class="button" href="/admin/subscriptions">Cancel</a></div></form></section>';View::layout('Edit Subscription',$body);
    }
    private static function invoiceVoid(string $guid,string $method): never { if($method!=='POST')Http::redirect('/admin/billing');$r=Store::one('SELECT status FROM invoices WHERE invoice_guid=?',[self::guidBin($guid)]);if(!$r)throw new \RuntimeException('Invoice not found.');if(!in_array($r['status'],['DRAFT','OPEN'],true))throw new \RuntimeException('Only DRAFT or OPEN invoices can be voided.');Store::exec('UPDATE invoices SET status="VOID",updated_at=NOW(6) WHERE invoice_guid=?',[self::guidBin($guid)]);Audit::log('invoice.void','invoice',$guid);self::redirect('/admin/billing','Invoice voided. Financial record preserved.'); }
}
