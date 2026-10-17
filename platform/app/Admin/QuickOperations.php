<?php
declare(strict_types=1);
namespace Digiti\Admin;

use Digiti\Core\{Audit,Config,DomainTools,Http,RedisStore,Store,Uuid,View};

/**
 * Day-to-day ePaper publisher operations. All POST requests are protected
 * by App::run()'s existing platform-admin, CSRF and session checks.
 *
 * Advanced management remains on the existing CRUD pages.
 */
final class QuickOperations
{
    private static function e(mixed $v): string { return View::esc($v); }
    private static function csrf(): string { return '<input type="hidden" name="_csrf" value="'.self::e(Http::csrf()).'">'; }
    private static function status(string $s): string {
        return '<span class="status '.strtolower(preg_replace('/[^a-z0-9]+/i','-',$s)).'">'.self::e(str_replace('_',' ',strtolower($s))).'</span>';
    }
    private static function redirect(string $path,string $notice): never { View::flash($notice);Http::redirect($path); }
    private static function app(): array {
        $row=Store::one('SELECT p.product_guid,a.application_guid FROM products p JOIN applications a ON a.product_guid=p.product_guid WHERE p.product_key="EPAPER" AND p.status="ACTIVE" AND a.application_key="city_epaper" AND a.status="ACTIVE" LIMIT 1');
        if(!$row)throw new \RuntimeException('An active EPAPER product and city_epaper application are required. Configure them in Applications first.');
        return $row;
    }
    private static function hostname(string $raw): string {
        $host=DomainTools::normalize($raw);
        if(!str_contains($host,'.') || $host==='digiti.in' || str_ends_with($host,'.digiti.in')) {
            throw new \InvalidArgumentException('Enter a publisher-owned domain, not a reserved Digiti hostname.');
        }
        return $host;
    }
    private static function addHostname(string $host,string $org,string $product,string $application,string $tenant): string {
        if(Store::value('SELECT domain_guid FROM domains WHERE normalized_hostname=?',[$host])) {
            throw new \InvalidArgumentException('This domain is already registered. Open Domains to review it.');
        }
        $guid=Uuid::v7();
        Store::exec('INSERT INTO domains(domain_guid,organisation_guid,product_guid,application_guid,product_tenant_guid,hostname,normalized_hostname,domain_type,status,is_primary,verification_token,created_at,updated_at) VALUES(?,?,?,?,?,?,?,"CUSTOMER_CUSTOM","AWAITING_DNS",0,?,NOW(6),NOW(6))',[
            Uuid::toBinary($guid),Uuid::toBinary($org),Uuid::toBinary($product),Uuid::toBinary($application),Uuid::toBinary($tenant),$host,$host,'digiti_'.bin2hex(random_bytes(16))
        ]);
        Store::exec('INSERT INTO application_redirect_uris(redirect_guid,application_guid,redirect_uri,status,created_at) VALUES(?,?,?,"ACTIVE",NOW(6)) ON DUPLICATE KEY UPDATE status="ACTIVE"',[
            Uuid::toBinary(Uuid::v7()),Uuid::toBinary($application),'https://'.$host.'/auth/callback'
        ]);
        return $guid;
    }
    private static function domainActions(array $d): string {
        $guid=self::e($d['domain_guid']);$status=(string)$d['status'];
        $base='/admin/domains/'.$guid;
        $h='<div class="actions compact">';
        $h.='<a class="button small" href="'.$base.'/edit">Edit</a>';
        if($status==='AWAITING_DNS'||$status==='REVALIDATION_FAILED'||$status==='SUSPENDED'){
            $h.='<form method="post" action="'.$base.'/verify" class="inline-form">'.self::csrf().'<button class="small">Check DNS</button></form>';
        } elseif(in_array($status,['ROUTING_VALID','SSL_PROVISIONING'],true)){
            $h.='<a class="button small" href="/admin/domains">Review SSL</a>';
        }
        if(in_array($status,['ACTIVE','ROUTING_VALID','SSL_PROVISIONING'],true)){
            $h.='<form method="post" action="/admin/quick-domains/'.$guid.'/disable" class="inline-form" data-confirm="Disable this publisher domain? Readers will lose access after caches expire.">'.self::csrf().'<button class="small">Disable</button></form>';
        }
        if($status!=='REMOVED'){
            $h.='<form method="post" action="'.$base.'/remove" class="inline-form" data-confirm="Remove this domain from active routing? This preserves historical records.">'.self::csrf().'<button class="small danger">Remove</button></form>';
        }
        return $h.'</div>';
    }
    public static function dashboard(): never {
        $metrics=[
            ['Publishers',(int)Store::value('SELECT COUNT(*) FROM product_tenants pt JOIN products p ON p.product_guid=pt.product_guid WHERE p.product_key="EPAPER" AND pt.status="ACTIVE"')],
            ['Live domains',(int)Store::value('SELECT COUNT(*) FROM domains d JOIN products p ON p.product_guid=d.product_guid WHERE p.product_key="EPAPER" AND d.status="ACTIVE"')],
            ['Pending DNS / SSL',(int)Store::value('SELECT COUNT(*) FROM domains d JOIN products p ON p.product_guid=d.product_guid WHERE p.product_key="EPAPER" AND d.status IN ("AWAITING_DNS","ROUTING_VALID","SSL_PROVISIONING","REVALIDATION_FAILED")')],
            ['Renewals due',(int)Store::value('SELECT COUNT(*) FROM subscriptions s JOIN product_tenants pt ON pt.product_tenant_guid=s.product_tenant_guid JOIN products p ON p.product_guid=pt.product_guid WHERE p.product_key="EPAPER" AND s.current_period_end <= DATE_ADD(NOW(6),INTERVAL 30 DAY) AND s.status IN ("ACTIVE","PAST_DUE","GRACE_PERIOD","EXPIRED")')]
        ];
        $head='<section class="quick-hero"><div><span class="eyebrow">EPAPER CONTROL CENTER</span><h2>Publishers, domains and renewals.<br>All in one place.</h2><p>Set up a newspaper, approve its domain and manage access without visiting multiple admin pages.</p></div><a class="primary hero-btn" href="/admin/quick-start">+ Add publisher</a></section>';
        $actions='<div class="quick-tiles"><a class="quick-tile" href="/admin/quick-start"><span class="quick-icon">＋</span><strong>New publisher</strong><small>Create publisher and domain together</small></a><a class="quick-tile" href="/admin/quick-domain"><span class="quick-icon">◎</span><strong>Add domain</strong><small>Connect another domain to a publisher</small></a><a class="quick-tile" href="#quick-renewals"><span class="quick-icon">↻</span><strong>Renew access</strong><small>Extend an approved subscription</small></a><a class="quick-tile" href="/admin/domains"><span class="quick-icon">✓</span><strong>Approve domains</strong><small>Verify DNS and review SSL</small></a></div>';
        $kpis='<div class="kpis quick-kpis">';foreach($metrics as $metric)$kpis.='<div class="kpi"><span>'.self::e($metric[0]).'</span><strong>'.$metric[1].'</strong></div>';$kpis.='</div>';
        $domains=Store::all('SELECT d.domain_guid,d.hostname,d.status,o.display_name publisher FROM domains d JOIN organisations o ON o.organisation_guid=d.organisation_guid JOIN products p ON p.product_guid=d.product_guid WHERE p.product_key="EPAPER" ORDER BY d.created_at DESC LIMIT 15');
        $rows='';foreach($domains as $d){$query=self::e(strtolower($d['hostname'].' '.$d['publisher'].' '.$d['status']));$rows.='<tr class="domain-row" data-search="'.$query.'"><td><strong>'.self::e($d['publisher']).'</strong><small class="cell-subtitle">'.self::e($d['hostname']).'</small></td><td>'.self::status($d['status']).'</td><td>'.self::domainActions($d).'</td></tr>';}
        if($rows==='')$rows='<tr><td colspan="3" class="muted">No publishers yet. Start by creating the first one.</td></tr>';
        $domainCard='<section class="card quick-panel"><div class="section-heading"><div><h2>Publisher domains</h2><p>Verify, edit, disable or remove a domain from one list.</p></div><a class="button" href="/admin/domains">View all domains →</a></div><label class="sr-only" for="domain-search">Search recent publisher domains</label><input id="domain-search" type="search" placeholder="Search publisher or domain..." autocomplete="off"><div class="table-wrap"><table class="quick-table"><thead><tr><th>Publisher / Domain</th><th>Status</th><th>Actions</th></tr></thead><tbody>'.$rows.'</tbody></table></div><p id="domain-empty" class="muted" hidden>No matching domains in these 15 recent entries. Try View all domains.</p></section>';
        $subscriptions=Store::all('SELECT s.subscription_guid,s.status,s.billing_state,s.service_access_state,s.cancel_at_period_end,s.current_period_end,pt.display_name tenant FROM subscriptions s JOIN product_tenants pt ON pt.product_tenant_guid=s.product_tenant_guid JOIN products p ON p.product_guid=pt.product_guid WHERE p.product_key="EPAPER" AND s.current_period_end <= DATE_ADD(NOW(6),INTERVAL 60 DAY) ORDER BY s.current_period_end ASC LIMIT 12');
        $renewRows='';foreach($subscriptions as $s){
            $id=self::e($s['subscription_guid']);$eligible=$s['status']==='ACTIVE'&&$s['billing_state']==='ACTIVE'&&$s['service_access_state']==='FULL'&&!$s['cancel_at_period_end'];
            $action=$eligible?'<form method="post" action="/admin/quick-renew/'.$id.'" class="inline-form quick-renew-form" data-confirm="Manually extend this approved subscription? No payment will be collected.">'.self::csrf().'<input type="hidden" name="acknowledged" value="yes"><label class="sr-only" for="duration-'.$id.'">Renewal duration</label><select id="duration-'.$id.'" name="months" aria-label="Renewal duration"><option value="1">+1 month</option><option value="3">+3 months</option><option value="12">+12 months</option></select><button class="small primary">Extend</button></form>':'<a href="/admin/subscriptions/'.$id.'/edit" class="button small">Review billing</a>';
            $renewRows.='<tr><td><strong>'.self::e($s['tenant']).'</strong><small class="cell-subtitle">'.self::status($s['status']).' '.self::e($s['billing_state']).'</small></td><td>'.self::e(substr((string)$s['current_period_end'],0,10)).'</td><td>'.$action.'</td></tr>';
        }
        if($renewRows==='')$renewRows='<tr><td colspan="3" class="muted">No subscription renewals are due in the next 60 days.</td></tr>';
        $renewCard='<section id="quick-renewals" class="card quick-panel"><div class="section-heading"><div><h2>Renewal desk</h2><p>Eligible active subscriptions can be extended after approval. Expired or unpaid accounts require a billing review.</p></div><a href="/admin/subscriptions" class="button">All subscriptions →</a></div><div class="table-wrap"><table class="quick-table"><thead><tr><th>Publisher</th><th>Period ends</th><th>Action</th></tr></thead><tbody>'.$renewRows.'</tbody></table></div><p class="muted hint">Extensions adjust service validity only. They do not charge the publisher or mark an invoice as paid.</p></section>';
        $body=$head.$kpis.$actions.$domainCard.$renewCard.'<p class="muted quick-foot">For plans, payments, users, and service accounts, use Advanced management in the navigation.</p><script>document.addEventListener("input",function(e){if(e.target.id!=="domain-search")return;let q=e.target.value.toLowerCase().trim(),visible=0;document.querySelectorAll(".domain-row").forEach(function(row){let show=row.dataset.search.includes(q);row.hidden=!show;if(show)visible++;});document.getElementById("domain-empty").hidden=visible!==0;});</script>';
        View::layout('ePaper Publisher Hub',$body,['subtitle'=>'The fastest way to manage your newspaper customers']);
    }
    public static function onboard(string $method): never {
        $app=self::app();
        $plans=Store::all('SELECT plan_guid,name FROM plans WHERE product_guid=? AND status="ACTIVE" ORDER BY name',[Uuid::toBinary($app['product_guid'])]);
        if($method==='POST'){
            $name=trim((string)($_POST['name']??''));$host=self::hostname((string)($_POST['hostname']??''));
            if((function_exists('mb_strlen')?mb_strlen($name):strlen($name))<2 || (function_exists('mb_strlen')?mb_strlen($name):strlen($name))>190)throw new \InvalidArgumentException('Publisher name must be between 2 and 190 characters.');
            $plan=(string)($_POST['plan_guid']??'');$approved=($_POST['approve_access']??'')==='yes';
            if($approved&&!$plan)throw new \InvalidArgumentException('Select a plan before approving service access.');
            if($approved&&!Store::value('SELECT plan_guid FROM plans WHERE plan_guid=? AND product_guid=? AND status="ACTIVE"',[Uuid::toBinary($plan),Uuid::toBinary($app['product_guid'])]))throw new \InvalidArgumentException('Selected plan is not an active ePaper plan.');
            [$org,$tenant,$domain]=Store::tx(static function()use($name,$host,$app,$plan,$approved){
                $org=Uuid::v7();$tenant=Uuid::v7();
                $slug=strtoupper(trim((string)preg_replace('/[^A-Z0-9]+/','_',strtoupper($name)),'_'));$code=substr($slug?:'PUBLISHER',0,48).'_'.strtoupper(bin2hex(random_bytes(4)));
                Store::exec('INSERT INTO organisations(organisation_guid,organisation_code,legal_name,display_name,status,created_at,updated_at) VALUES(?,?,?,?,"ACTIVE",NOW(6),NOW(6))',[Uuid::toBinary($org),$code,$name,$name]);
                Store::exec('INSERT INTO product_tenants(product_tenant_guid,organisation_guid,product_guid,display_name,status,created_at,updated_at) VALUES(?,?,?,?,"ACTIVE",NOW(6),NOW(6))',[Uuid::toBinary($tenant),Uuid::toBinary($org),Uuid::toBinary($app['product_guid']),$name]);
                $domain=self::addHostname($host,$org,$app['product_guid'],$app['application_guid'],$tenant);
                if($approved){
                    $sub=Uuid::v7();
                    Store::exec('INSERT INTO subscriptions(subscription_guid,product_tenant_guid,plan_guid,status,billing_state,service_access_state,current_period_start,current_period_end,created_at,updated_at) VALUES(?,?,?,"ACTIVE","ACTIVE","FULL",NOW(6),DATE_ADD(NOW(6),INTERVAL 1 MONTH),NOW(6),NOW(6))',[Uuid::toBinary($sub),Uuid::toBinary($tenant),Uuid::toBinary($plan)]);
                }
                return [$org,$tenant,$domain];
            });
            Audit::log('publisher.quick_create','product_tenant',$tenant,'SUCCESS',['domain_guid'=>$domain,'organisation_guid'=>$org,'manual_access_approved'=>$approved]);
            self::redirect('/admin/quick-domains/'.$domain,'Publisher created. Follow the three steps to activate its domain.');
        }
        $select='<option value="">No access yet — configure later</option>';foreach($plans as $p)$select.='<option value="'.self::e($p['plan_guid']).'">'.self::e($p['name']).'</option>';
        $body='<section class="card quick-form-card"><p class="eyebrow">GUIDED SETUP · 1 OF 2</p><h2>Create publisher and first domain</h2><p class="muted">Only two details are required. We automatically connect the new organisation to the ePaper product and create its tenant.</p><form method="post" class="quick-form">'.self::csrf().'<label>Publisher / newspaper name<input name="name" maxlength="190" placeholder="Pune Mirror" autocomplete="organization" required></label><label>Publisher ePaper domain<input name="hostname" placeholder="epaper.punemirror.in" required><small>Enter only the hostname, without https://</small></label><fieldset class="quick-optional"><legend>Optional: grant service access now</legend><label>Plan<select name="plan_guid">'.$select.'</select></label><label class="check"><input type="checkbox" name="approve_access" value="yes"> I confirm this service access has been approved manually. No payment is collected here.</label></fieldset><div class="form-action"><button class="primary">Create publisher →</button><a href="/admin" class="button">Cancel</a></div></form></section><section class="card quick-guide"><h2>What happens next?</h2><ol><li><strong>Publisher created:</strong> organisation, tenant and requested domain are registered together.</li><li><strong>DNS verification:</strong> point the domain to the configured CNAME target or add the verification TXT record.</li><li><strong>SSL and activation:</strong> ensure Cloudways / Cloudflare routing and HTTPS are ready, then activate the domain.</li></ol><p class="muted">Publisher ownership and TLS are not approved simply by submitting this form.</p></section>';
        View::layout('Add publisher',$body,['subtitle'=>'Simple onboarding — no technical IDs required']);
    }
    public static function addDomain(string $method): never {
        $app=self::app();
        $tenants=Store::all('SELECT pt.product_tenant_guid,CONCAT(o.display_name," — ",pt.display_name) label,pt.organisation_guid FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid WHERE pt.product_guid=? AND pt.status="ACTIVE" AND o.status="ACTIVE" ORDER BY o.display_name',[Uuid::toBinary($app['product_guid'])]);
        if($method==='POST'){
            $tenant=(string)($_POST['tenant_guid']??'');$host=self::hostname((string)($_POST['hostname']??''));
            $picked=null;foreach($tenants as $t)if($t['product_tenant_guid']===$tenant){$picked=$t;break;}
            if(!$picked)throw new \InvalidArgumentException('Select an active publisher.');
            $domain=Store::tx(static fn()=>self::addHostname($host,$picked['organisation_guid'],$app['product_guid'],$app['application_guid'],$tenant));
            Audit::log('domain.quick_create','domain',$domain);
            self::redirect('/admin/quick-domains/'.$domain,'Domain registered. Follow DNS verification and HTTPS steps.');
        }
        $options='<option value="">Select publisher</option>';foreach($tenants as $t)$options.='<option value="'.self::e($t['product_tenant_guid']).'">'.self::e($t['label']).'</option>';
        $body='<section class="card quick-form-card"><p class="eyebrow">DOMAIN SETUP</p><h2>Add another publisher domain</h2><p class="muted">Choose the publisher and enter the new domain. Everything else is linked automatically.</p><form method="post" class="quick-form">'.self::csrf().'<label>Publisher<select name="tenant_guid" required>'.$options.'</select></label><label>Additional domain<input name="hostname" placeholder="epaper.civicmirror.in" required></label><div class="form-action"><button class="primary">Register domain →</button><a class="button" href="/admin">Cancel</a></div></form></section><section class="card quick-guide"><h2>After registration</h2><p>Set the publisher DNS, complete ownership verification and TLS provisioning, then activate the domain in Domains. This step never points unrelated publishers to the same tenant.</p></section>';
        View::layout('Add domain',$body,['subtitle'=>'Link a hostname to an existing ePaper publisher']);
    }

    /**
     * Plain-language setup checklist for a single domain.
     * Ownership validation and HTTPS provisioning remain separate explicit steps.
     */
    public static function domainDetail(string $guid): never {
        $d=Store::one('SELECT d.domain_guid,d.hostname,d.status,d.verification_token,d.last_checked_at,d.edge_metadata,o.display_name publisher,pt.display_name tenant FROM domains d JOIN products p ON p.product_guid=d.product_guid JOIN organisations o ON o.organisation_guid=d.organisation_guid JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid WHERE d.domain_guid=? AND p.product_key="EPAPER"',[Uuid::toBinary($guid)]);
        if(!$d)throw new \RuntimeException('ePaper domain not found.');
        $host=self::e($d['hostname']);$target=self::e(Config::get('app.domains_target','domains.digiti.in'));
        $token=self::e($d['verification_token']);$status=self::status($d['status']);
        $verified=in_array($d['status'],['ROUTING_VALID','SSL_PROVISIONING','ACTIVE'],true);
        $active=$d['status']==='ACTIVE';
        $step='<div class="quick-steps"><section class="card"><span class="eyebrow">STEP 1</span><h2>Connect domain to ePaper</h2><p>Add either the CNAME record or the TXT ownership record in your DNS provider.</p><div class="dns-record"><span>CNAME</span><code>'.$host.' → '.$target.'</code></div><div class="dns-record"><span>TXT</span><code>_digiti-verification.'.$host.' → '.$token.'</code></div><p class="muted">DNS changes can take time to propagate. For Cloudflare for SaaS, follow the displayed edge validation records too.</p></section>';
        $step.='<section class="card"><span class="eyebrow">STEP 2</span><h2>Verify ownership</h2><p>Domain status: '.$status.'</p>';
        if(!$active&&$d['status']!=='REMOVED')$step.='<form method="post" action="/admin/domains/'.self::e($guid).'/verify">'.self::csrf().'<button class="primary">Check DNS now</button></form>';
        elseif($active)$step.='<p class="status active">Active</p>';
        else $step.='<p class="muted">Removed domains must be reviewed before registering them again.</p>';
        $step.='</section><section class="card"><span class="eyebrow">STEP 3</span><h2>HTTPS and go live</h2><p>'.($verified?'DNS routing has been recorded.':'First complete domain ownership validation.').' Ensure your Cloudways/Cloudflare edge points to the existing ePaper application and the HTTPS certificate is valid.</p><a class="button" href="/admin/domains">Review SSL and activate →</a><p class="muted">Activating a hostname in Platform is not a substitute for edge TLS and origin routing.</p></section></div>';
        $body='<section class="quick-domain-head"><div><p class="eyebrow">PUBLISHER DOMAIN</p><h2>'.$host.'</h2><p>'.self::e($d['publisher']).' · '.self::e($d['tenant']).' · '.$status.'</p></div><a href="/admin/domains/'.self::e($guid).'/edit" class="button">Edit domain</a></section>'.$step.'<p><a class="button" href="/admin">← Back to Publisher Hub</a></p>';
        View::layout('Domain setup',$body,['subtitle'=>'DNS → Verify → HTTPS → Activate']);
    }

    public static function disableDomain(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin');
        $row=Store::one('SELECT d.hostname,d.status,p.product_key FROM domains d JOIN products p ON p.product_guid=d.product_guid WHERE d.domain_guid=?',[Uuid::toBinary($guid)]);
        if(!$row||$row['product_key']!=='EPAPER')throw new \RuntimeException('ePaper domain not found.');
        if(!in_array($row['status'],['ACTIVE','ROUTING_VALID','SSL_PROVISIONING'],true))throw new \RuntimeException('Only routed domains can be disabled from the dashboard.');
        $n=Store::exec('UPDATE domains SET status="SUSPENDED",updated_at=NOW(6) WHERE domain_guid=? AND status=?',[Uuid::toBinary($guid),$row['status']]);
        if($n!==1)throw new \RuntimeException('Domain status changed. Refresh the dashboard.');
        RedisStore::del('domain:'.$row['hostname']);
        Audit::log('domain.quick_disable','domain',$guid);
        self::redirect('/admin','Domain disabled in Platform. Check ePaper and edge cache expiry for complete revocation.');
    }
    public static function renew(string $guid,string $method): never {
        if($method!=='POST')Http::redirect('/admin');
        if(($_POST['acknowledged']??'')!=='yes')throw new \InvalidArgumentException('Explicit manual renewal approval is required.');
        $months=(int)($_POST['months']??0);if(!in_array($months,[1,3,12],true))throw new \InvalidArgumentException('Select 1, 3 or 12 months.');
        $tenant=Store::tx(static function()use($guid,$months){
            $s=Store::one('SELECT s.product_tenant_guid,s.status,s.billing_state,s.service_access_state,s.cancel_at_period_end,p.product_key,pt.status tenant_status,o.status org_status FROM subscriptions s JOIN product_tenants pt ON pt.product_tenant_guid=s.product_tenant_guid JOIN organisations o ON o.organisation_guid=pt.organisation_guid JOIN products p ON p.product_guid=pt.product_guid WHERE s.subscription_guid=? FOR UPDATE',[Uuid::toBinary($guid)]);
            if(!$s||$s['product_key']!=='EPAPER')throw new \RuntimeException('ePaper subscription not found.');
            if($s['status']!=='ACTIVE'||$s['billing_state']!=='ACTIVE'||$s['service_access_state']!=='FULL'||$s['cancel_at_period_end']||$s['tenant_status']!=='ACTIVE'||$s['org_status']!=='ACTIVE')throw new \RuntimeException('Subscription requires billing / access review before renewal.');
            Store::exec('UPDATE subscriptions SET current_period_end=DATE_ADD(GREATEST(current_period_end,NOW(6)),INTERVAL ? MONTH),version=version+1,updated_at=NOW(6) WHERE subscription_guid=?',[$months,Uuid::toBinary($guid)]);
            return $s['product_tenant_guid'];
        });
        RedisStore::del('entitlements:'.$tenant);
        Audit::log('subscription.manual_extend','subscription',$guid,'SUCCESS',['months'=>$months,'approval'=>'platform_admin']);
        self::redirect('/admin#quick-renewals','Service period extended by '.$months.' month(s). No payment was collected.');
    }
}
