<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\{Audit,Auth,DomainTools,Http,Store,Uuid,View};

/**
 * Super-admin-only diagnostics and explicitly confirmed scoped service permission fix.
 * A healthy hostname binding does not prove machine auth.direct scope, user credentials
 * or active membership to the hostname's publisher organisation.
 */
final class PublisherLoginCheck
{
    private static function e(mixed $v): string{return View::esc($v);}

    private static function superAdmin(): void
    {
        $admin=Auth::requireAdmin();
        $role=(string)Store::value(
            'SELECT role_key FROM platform_admins WHERE admin_guid=? AND status="ACTIVE"',
            [Uuid::toBinary((string)$admin['admin_guid'])]
        );
        if($role!=='SUPER_ADMIN'){
            http_response_code(403);
            View::layout('Access restricted',
                '<section class="card"><h2>Super administrator required</h2>'
                .'<p>Publisher identity and service-account diagnostics are restricted.</p></section>');
        }
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
    }

    private static function resolveHost(string $hostname): ?array
    {
        if($hostname==='')return null;
        $host=DomainTools::publicHostname($hostname);
        return Store::one(
            'SELECT d.domain_guid,d.hostname,d.status domain_status,d.product_guid,d.application_guid,'
            .'d.product_tenant_guid,d.organisation_guid,'
            .'a.name application_name,a.status application_status,a.oauth_client_id,'
            .'p.name product_name,p.status product_status,'
            .'pt.display_name tenant_name,pt.status tenant_status,'
            .'o.display_name organisation_name,o.status organisation_status '
            .'FROM domains d '
            .'JOIN applications a ON a.application_guid=d.application_guid AND a.product_guid=d.product_guid '
            .'JOIN products p ON p.product_guid=d.product_guid '
            .'JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid '
            .'AND pt.product_guid=d.product_guid AND pt.organisation_guid=d.organisation_guid '
            .'JOIN organisations o ON o.organisation_guid=d.organisation_guid '
            .'WHERE d.normalized_hostname=? AND d.status<>"REMOVED" LIMIT 1',
            [$host]
        );
    }

    /** Only status data; this helper cannot verify a password or MFA assertion. */
    public static function assess(array $domain,?array $user,?array $membership): array
    {
        $checks=[
            'domain'=>in_array((string)($domain['domain_status']??''),['ACTIVE','ROUTING_VALID','SSL_PROVISIONING'],true),
            'application'=>(string)($domain['application_status']??'')==='ACTIVE' &&
                trim((string)($domain['oauth_client_id']??''))!=='',
            'product'=>(string)($domain['product_status']??'')==='ACTIVE',
            'tenant'=>(string)($domain['tenant_status']??'')==='ACTIVE',
            'organisation'=>(string)($domain['organisation_status']??'')==='ACTIVE',
            'user'=>$user!==null && (string)($user['status']??'')==='ACTIVE',
            'membership'=>$membership!==null && (string)($membership['status']??'')==='ACTIVE',
            'publisher_role'=>$membership!==null && in_array(
                (string)($membership['role_key']??''),
                ['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','OPERATOR'],true),
        ];
        return $checks;
    }

    public static function index(string $method): never
    {
        self::superAdmin();
        $host='';
        $email='';
        $record=null;
        $error='';
        $checks=[];
        $accounts=[];
        $user=null;
        $membership=null;
        $adminSeparate=false;

        if($method==='POST'){
            // App::run() verifies CSRF for every admin POST.
            $host=trim((string)($_POST['hostname']??''));
            $email=strtolower(trim((string)($_POST['email']??'')));
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
                $error='Enter a valid publisher user email.';
            } else {
                try{$record=self::resolveHost($host);}
                catch(\InvalidArgumentException){$error='Enter a valid registered public hostname.';}
                if(!$record && $error==='')$error='No active or pending registered domain was found for this hostname.';
                if($record){
                    $user=Store::one(
                        'SELECT user_guid,email,display_name,status,mfa_enabled FROM platform_users WHERE email=? LIMIT 1',
                        [$email]
                    );
                    $adminSeparate=(bool)Store::value(
                        'SELECT 1 FROM platform_admins WHERE email=? AND status="ACTIVE" LIMIT 1',[$email]
                    );
                    if($user){
                        $membership=Store::one(
                            'SELECT membership_guid,role_key,status FROM memberships '
                            .'WHERE user_guid=? AND organisation_guid=? LIMIT 1',
                            [Uuid::toBinary((string)$user['user_guid']),
                             Uuid::toBinary((string)$record['organisation_guid'])]
                        );
                    }
                    $checks=self::assess($record,$user,$membership);
                    $accounts=Store::all(
                        'SELECT service_account_guid,name,status,scopes,last_used_at '
                        .'FROM service_accounts WHERE product_guid=? AND status="ACTIVE" '
                        .'ORDER BY last_used_at DESC,created_at DESC LIMIT 50',
                        [Uuid::toBinary((string)$record['product_guid'])]
                    );
                }
            }
        }
        $form='<section class="card"><h2>Check publisher login</h2>'
            .'<p>Use the exact hostname from the ePaper address bar and the email the publisher is trying to sign in with.</p>'
            .'<form action="/admin/publisher-login-check" method="post" class="form-grid">'
            .'<input type="hidden" name="_csrf" value="'.self::e(Http::csrf()).'">'
            .'<label>Registered ePaper hostname<input name="hostname" value="'.self::e($host?:'epaper.digiti.in').'" required></label>'
            .'<label>Publisher email<input type="email" name="email" value="'.self::e($email).'" autocomplete="off" required></label>'
            .'<div class="form-action"><button class="primary" type="submit">Run login checks</button></div>'
            .'</form></section>';
        $content=$form;
        if($error!=='')$content.='<section class="card"><p>'.self::e($error).'</p></section>';
        if($record){
            $body='<section class="card"><h2>Selected domain and tenant</h2>'
                .'<p><strong>Hostname:</strong> '.self::e($record['hostname'])
                .' · <strong>Application:</strong> '.self::e($record['application_name'])
                .' · <strong>Tenant:</strong> '.self::e($record['tenant_name'])
                .' · <strong>Organisation:</strong> '.self::e($record['organisation_name'])
                .'</p><div class="table-wrap"><table><thead><tr><th>Prerequisite</th><th>Result</th></tr></thead><tbody>';
            $labels=[
                'domain'=>'Registered domain is routable',
                'application'=>'Application ACTIVE with OAuth client',
                'product'=>'Product ACTIVE',
                'tenant'=>'Publisher tenant ACTIVE',
                'organisation'=>'Publisher organisation ACTIVE',
                'user'=>'Email exists as ACTIVE Platform publisher user',
                'membership'=>'ACTIVE membership in this hostname’s organisation',
                'publisher_role'=>'Role permits publisher dashboard access',
            ];
            foreach($checks as $key=>$pass)
                $body.='<tr><td>'.self::e($labels[$key]??$key).'</td>'
                    .'<td>'.($pass?'PASS':'NEEDS ATTENTION').'</td></tr>';
            $body.='</tbody></table></div>';
            if($user && !empty($user['mfa_enabled']))
                $body.='<p>Authenticator (MFA) is enabled for this user; a valid current code is required.</p>';
            if($adminSeparate && !$user)
                $body.='<p><strong>Account type:</strong> This address is a Platform administrator, '
                .'not a publisher user in the separate Platform user registry. '
                .'Platform administrator login does not automatically authorize access to any publisher tenant.</p>';
            if(!$user)
                $body.='<p>Use <a href="/admin/users">Team & Access</a> to locate or create the authorized publisher user.</p>';
            if($user && empty($checks['membership']))
                $body.='<p>Assign an approved existing user to this organisation through '
                .'<a href="/admin/onboarding?domain='.rawurlencode((string)$record['domain_guid']).'">Publisher onboarding</a>. '
                .'Do not reassign the hostname or borrow another publisher’s membership.</p>';
            if($user && !empty($checks['membership']) && empty($checks['publisher_role']))
                $body.='<p>This user has a membership but their role does not permit ePaper administration. '
                .'Review the intended role; do not auto-escalate access.</p>';
            $body.='<p><small>These checks cannot verify the submitted password, MFA code, '
                .'the live ePaper config token, browser session or remote Cloudways deployment. '
                .'For a valid user with all checks passing, investigate Platform audit records '
                .'and the actual POST response without logging passwords.</small></p></section>';
            $content.=$body;
            $content.='<section class="card"><h2>Machine authentication for this product</h2>'
                .'<p>Domain resolution uses <code>domains.resolve</code>, but the publisher password-login API '
                .'requires <code>auth.direct</code>. The connection can be READY without that scope. '
                .'The registry cannot identify which token is in the private ePaper configuration; '
                .'verify the exact service-account identity before changing permissions.</p>';
            if(!$accounts)$content.='<p>No ACTIVE product-scoped service account exists for this product. '
                .'<a href="/admin/service-accounts">Create one</a> before continuing.</p>';
            else{
                $content.='<div class="table-wrap"><table><thead><tr>'
                    .'<th>Product-scoped account</th><th>Last used</th><th>Direct login scope</th><th>Action</th>'
                    .'</tr></thead><tbody>';
                foreach($accounts as $account){
                    $scopes=json_decode((string)$account['scopes'],true);
                    $has=is_array($scopes) &&
                        (in_array('auth.direct',$scopes,true)||in_array('*',$scopes,true));
                    $content.='<tr><td>'.self::e($account['name']).'</td>'
                        .'<td>'.self::e($account['last_used_at']??'Never').'</td>'
                        .'<td>'.($has?'Granted':'Missing').'</td><td>';
                    if(!$has){
                        $content.='<form method="post" action="/admin/publisher-login-check/grant-direct">'
                            .'<input type="hidden" name="_csrf" value="'.self::e(Http::csrf()).'">'
                            .'<input type="hidden" name="hostname" value="'.self::e($record['hostname']).'">'
                            .'<input type="hidden" name="service_account_guid" value="'.self::e($account['service_account_guid']).'">'
                            .'<input type="hidden" name="confirm_account" value="'.self::e($account['name']).'">'
                            .'<label><input type="checkbox" name="confirm_scope" value="1" required>'
                            .' I verified this is the credential used by ePaper and authorize <code>auth.direct</code></label>'
                            .'<button type="submit" class="button">Grant scope</button></form>';
                    }else $content.='Already granted';
                    $content.='</td></tr>';
                }
                $content.='</tbody></table></div>';
            }
            $content.='<p><a class="button" href="/admin/service-accounts">Manage all service accounts</a> '
                .'<a class="button" href="/admin/audit">Audit log</a></p>'
                .'</section>';
        }
        View::layout('Publisher sign-in diagnostics',$content,[
            'subtitle'=>'Verify domain, Platform user, membership and machine scopes without resetting tenants or secrets',
        ]);
    }

    public static function grant(string $method): never
    {
        self::superAdmin();
        if($method!=='POST')Http::redirect('/admin/publisher-login-check');
        if(($_POST['confirm_scope']??'')!=='1')
            throw new \InvalidArgumentException('Explicit auth.direct scope approval required.');
        $host=DomainTools::publicHostname((string)($_POST['hostname']??''));
        $domain=self::resolveHost($host);
        if(!$domain || !self::assess($domain,null,null)['application'] ||
            !self::assess($domain,null,null)['product'])
            throw new \RuntimeException('Active application and product are required.');
        $guid=trim((string)($_POST['service_account_guid']??''));
        $name=(string)($_POST['confirm_account']??'');
        $bin=Uuid::toBinary($guid);
        $productBin=Uuid::toBinary((string)$domain['product_guid']);
        $added=Store::tx(static function()use($bin,$productBin,$name):bool {
            $row=Store::one(
                'SELECT service_account_guid,product_guid,name,status,scopes '
                .'FROM service_accounts WHERE service_account_guid=? AND product_guid=? FOR UPDATE',
                [$bin,$productBin]
            );
            if(!$row || $row['status']!=='ACTIVE' ||
                $name==='' || !hash_equals((string)$row['name'],$name))
                throw new \InvalidArgumentException('Confirm the current ACTIVE product-scoped service account.');
            $scopes=json_decode((string)$row['scopes'],true);
            if(!is_array($scopes))throw new \RuntimeException('Service scopes require manual review.');
            if(in_array('auth.direct',$scopes,true)||in_array('*',$scopes,true))return false;
            $scopes[]='auth.direct';
            Store::exec(
                'UPDATE service_accounts SET scopes=? WHERE service_account_guid=? AND product_guid=? AND status="ACTIVE"',
                [json_encode(array_values(array_unique($scopes))),$bin,$productBin]
            );
            return true;
        });
        if($added){
            Audit::log('service_account.auth_direct_granted','service_account',$guid,'SUCCESS',[
                'hostname'=>$host,'product_guid'=>$domain['product_guid'],'scope'=>'auth.direct'
            ]);
        }
        View::flash($added?'auth.direct granted to the selected existing service account. No token rotation or tenant changes were made.'
            :'The selected service account already has auth.direct.');
        Http::redirect('/admin/publisher-login-check');
    }
}
