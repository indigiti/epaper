<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\{Auth,Config,Store,Uuid,View};

/**
 * Admin-only read-only ePaper integration guide.
 *
 * Platform does NOT have access to the separate Cloudways ePaper installation,
 * its config.php/runtime.php, or cleartext service credentials. Never pretend
 * that a generated preview is the live private configuration.
 */
final class ConnectionSetup
{
    private const REQUIRED_SCOPES = [
        'domains.resolve', 'tenant.read', 'access.read', 'entitlements.read',
        'identity.verify', 'auth.exchange', 'usage.write', 'heartbeat.write',
        'audit.write', 'errors.write',
    ];

    /**
     * Copy-ready additive private override. Existing database, Redis, workers,
     * app key, uploads and tenant data remain untouched. Never include secrets.
     */
    public static function runtimeSnippet(array $app): string
    {
        $canonical=rtrim(trim((string)($app['base_url']??'')),'/');
        if(!filter_var($canonical,FILTER_VALIDATE_URL) ||
            strtolower((string)parse_url($canonical,PHP_URL_SCHEME))!=='https'){
            throw new \InvalidArgumentException('Select an application with a valid HTTPS Base URL.');
        }

        $platform=rtrim((string)Config::get('app.url','https://platform.digiti.in'),'/');
        $override=[
            'app'=>['url'=>$canonical],
            'platform'=>[
                'url'=>$platform,
                'product_key'=>(string)($app['product_key']??''),
                'product_guid'=>(string)($app['product_guid']??''),
                'application_key'=>(string)($app['application_key']??''),
                'application_guid'=>(string)($app['application_guid']??''),
                'oauth_client_id'=>(string)($app['oauth_client_id']??''),
                'service_token'=>'PASTE_NEW_SERVICE_TOKEN_HERE',
                // Publisher aliases MUST resolve their own registered tenants.
                'default_tenant_guid'=>'',
                'default_organisation_guid'=>'',
                'allow_canonical_fallback'=>false,
            ],
        ];
        return "<?php\n// Private ePaper config/runtime.php override.\n"
            ."// Replace the service_token placeholder using a freshly issued secret.\n"
            ."// Do NOT upload this file into the public webroot or commit it to Git.\n"
            ."return ".var_export($override,true).";\n";
    }

    /** Pure and testable scope check; unknown or missing scopes fail closed. */
    public static function missingScopes(mixed $scopes): array
    {
        if(!is_array($scopes))return self::REQUIRED_SCOPES;
        if(in_array('*',$scopes,true))return [];
        return array_values(array_diff(self::REQUIRED_SCOPES,$scopes));
    }

    private static function e(mixed $value): string{return View::esc($value);}

    public static function index(): never
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
                .'<p>Only a Platform super administrator may review application integration configuration.</p></section>');
        }
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: private, no-store, max-age=0');

        $apps=Store::all(
            'SELECT a.application_guid,a.application_key,a.name,a.base_url,a.oauth_client_id,'
            .'a.product_guid,a.status,p.name product_name,p.product_key,p.status product_status '
            .'FROM applications a JOIN products p ON p.product_guid=a.product_guid '
            .'WHERE a.status="ACTIVE" AND p.status="ACTIVE" '
            .'ORDER BY a.name,a.base_url LIMIT 200'
        );
        if(!$apps){
            View::layout('Application connection setup',
                '<section class="card"><h2>No active application</h2><p>Register an active product and application first. '
                .'Do not recover archived products solely to fix a stale credential.</p>'
                .'<a class="button" href="/admin/applications">Review applications</a></section>');
        }
        $selected=(string)($_GET['application_guid']??'');
        if($selected!==''&&!preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i',$selected))
            throw new \InvalidArgumentException('Invalid application GUID.');
        $chosen=null;
        foreach($apps as $app){
            if($selected!=='' && strcasecmp((string)$app['application_guid'],$selected)===0){
                $chosen=$app;
                break;
            }
        }
        if($selected!==''&&!$chosen)throw new \InvalidArgumentException('Application is not active or is unavailable.');
        if(!$chosen){
            foreach($apps as $app){
                $host=strtolower((string)(parse_url((string)$app['base_url'],PHP_URL_HOST)?:''));
                if($host==='epaper.digiti.in'){$chosen=$app;break;}
            }
            $chosen??=$apps[0];
        }
        $appGuid=(string)$chosen['application_guid'];
        $productGuid=(string)$chosen['product_guid'];
        $options='';
        foreach($apps as $app){
            $id=(string)$app['application_guid'];
            $selectedAttr=$id===$appGuid?' selected':'';
            $options.='<option value="'.self::e($id).'"'.$selectedAttr.'>'
                .self::e((string)$app['name'].' · '.(string)$app['product_name'].' · '.(string)$app['base_url'])
                .'</option>';
        }
        $choose='<section class="card"><h2>Select the installed application</h2>'
            .'<p>These values come from the active Platform registry, not from the separate Cloudways file.</p>'
            .'<form method="get" action="/admin/app-connections" class="form-grid">'
            .'<label>Application<select name="application_guid" required>'.$options.'</select></label>'
            .'<div class="form-action"><button class="primary" type="submit">Show configuration</button></div></form></section>';

        $domains=Store::all(
            'SELECT d.hostname,d.status,d.domain_type,d.product_tenant_guid,'
            .'pt.display_name tenant_name,pt.status tenant_status,'
            .'o.display_name organisation_name,o.status organisation_status '
            .'FROM domains d '
            .'JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid '
            .'JOIN organisations o ON o.organisation_guid=d.organisation_guid '
            .'WHERE d.application_guid=? AND d.status<>"REMOVED" '
            .'ORDER BY d.hostname LIMIT 150',
            [Uuid::toBinary($appGuid)]
        );
        $canonicalHost=strtolower((string)(parse_url((string)$chosen['base_url'],PHP_URL_HOST)?:''));
        $hasCanonical=false;
        $domainRows='';
        foreach($domains as $domain){
            $isCanonical=strtolower((string)$domain['hostname'])===$canonicalHost;
            if($isCanonical)$hasCanonical=true;
            $ok=in_array((string)$domain['status'],['ACTIVE','ROUTING_VALID','SSL_PROVISIONING'],true)
                && $domain['tenant_status']==='ACTIVE' && $domain['organisation_status']==='ACTIVE';
            $domainRows.='<tr><td><strong>'.self::e($domain['hostname']).'</strong>'
                .($isCanonical?' <small>Canonical</small>':'').'</td>'
                .'<td>'.self::e($domain['organisation_name']).'</td>'
                .'<td>'.self::e($domain['tenant_name']).'<small style="display:block">'
                .self::e($domain['product_tenant_guid']).'</small></td>'
                .'<td>'.self::e($domain['status']).($ok?'':' · needs attention').'</td></tr>';
        }
        $domainSection='<section class="card"><h2>Hostname → tenant bindings</h2>'
            .'<p>One application may serve many domains. Every hostname must resolve to its own registered tenant. '
            .'A canonical default tenant is not applied to customer aliases.</p>'
            .(!$hasCanonical?'<p><strong>Check:</strong> No non-archived canonical hostname registration was found for this application.</p>':'')
            .($domains?'<div class="table-wrap"><table><thead><tr><th>Hostname</th><th>Organisation</th><th>Tenant</th><th>Domain status</th></tr></thead>'
            .'<tbody>'.$domainRows.'</tbody></table></div>'
            :'<p>No non-archived domains are registered for this application.</p>')
            .'<a class="button" href="/admin/onboarding">Open publisher onboarding</a></section>';

        $accounts=Store::all(
            'SELECT service_account_guid,name,status,scopes,last_used_at '
            .'FROM service_accounts WHERE product_guid=? AND status<>"REMOVED" '
            .'ORDER BY created_at DESC LIMIT 50',
            [Uuid::toBinary($productGuid)]
        );
        $accountRows='';
        foreach($accounts as $account){
            $scopes=json_decode((string)$account['scopes'],true);
            $missing=self::missingScopes($scopes);
            $ready=$account['status']==='ACTIVE' && !$missing;
            $accountRows.='<tr><td>'.self::e($account['name']).'</td>'
                .'<td>'.self::e($account['status']).'</td>'
                .'<td>'.($ready?'Scope-ready':self::e($missing?'Missing: '.implode(', ',$missing):'Inactive')).'</td>'
                .'<td>'.self::e($account['last_used_at']??'Never').'</td></tr>';
        }
        $accountSection='<section class="card"><h2>Product-scoped service accounts</h2>'
            .'<p>Platform stores only token hashes: it cannot display an existing service-token secret. '
            .'A green scope check does not prove the token configured in Cloudways is valid.</p>'
            .($accounts?'<div class="table-wrap"><table><thead><tr><th>Account</th><th>Status</th><th>Scopes</th><th>Last used</th></tr></thead><tbody>'
                .$accountRows.'</tbody></table></div>'
                :'<p>No non-archived product-scoped service account was found. Create one for this active product.</p>')
            .'<a class="button" href="/admin/service-accounts">Create / rotate service token</a>'
            .'<p><small>Required scopes: '.self::e(implode(', ',self::REQUIRED_SCOPES)).'</small></p></section>';

        $snippet=self::runtimeSnippet($chosen);
        $configuration='<section class="card"><h2>Copy-ready ePaper private configuration</h2>'
            .'<p><strong>Application:</strong> '.self::e($chosen['name'])
            .' · <strong>Product:</strong> '.self::e($chosen['product_name'])
            .' · <strong>Base URL:</strong> '.self::e($chosen['base_url']).'</p>'
            .'<p><strong>Important:</strong> This is a generated private <code>config/runtime.php</code> override, '
            .'not the current Cloudways <code>config.php</code>. The Platform server cannot read or edit that file.</p>'
            .'<p><strong>Replace <code>PASTE_NEW_SERVICE_TOKEN_HERE</code> with a newly issued token before using this file.</strong> '
            .'Never upload the secret to a public directory or Git repository.</p>'
            .'<label for="connection-php">PHP configuration to merge into your private ePaper installation</label>'
            .'<textarea id="connection-php" readonly spellcheck="false" rows="24" style="width:100%;font-family:monospace;font-size:13px;max-width:100%;white-space:pre">'
            .self::e($snippet).'</textarea>'
            .'<div class="actions"><button type="button" id="copy-connection-php">Copy PHP configuration</button>'
            .'<span id="copy-connection-status" role="status" aria-live="polite"></span></div>'
            .'<p><small>If <code>config/runtime.php</code> already exists, merge the shown <code>app</code> and '
            .'<code>platform</code> entries instead of replacing other runtime settings. '
            .'If <code>EPDI_PRIVATE_CONFIG_DIR</code> is set, update that private directory. '
            .'The loader applies <code>runtime.php</code> after <code>config.php</code>.</small></p>'
            .'<p>After updating Cloudways, verify '
            .'<a href="'.self::e(rtrim((string)$chosen['base_url'],'/').'/health/platform').'" target="_blank" rel="noopener noreferrer">Platform binding</a>'
            .' and <a href="'.self::e(rtrim((string)$chosen['base_url'],'/').'/health/ready').'" target="_blank" rel="noopener noreferrer">application readiness</a>. '
            .'Check each publisher hostname independently.</p></section>'
            .'<script>(function(){const b=document.getElementById("copy-connection-php");'
            .'const t=document.getElementById("connection-php");'
            .'const s=document.getElementById("copy-connection-status");'
            .'if(!b||!t)return;b.addEventListener("click",async function(){'
            .'try{await navigator.clipboard.writeText(t.value);s.textContent="Copied";}'
            .'catch(e){t.focus();t.select();s.textContent="Select the text and copy it manually";}'
            .'});})();</script>';

        View::layout('Application connection setup',$choose.$configuration.$accountSection.$domainSection,[
            'subtitle'=>'Read-only Platform registry values for safely updating the separate Cloudways ePaper config',
        ]);
    }
}
