<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\{Config,View};

/**
 * Publisher onboarding presentation. Works entirely from existing Platform records;
 * no simulated statuses, extra database tables or client-side API dependencies.
 */
final class OnboardingDashboard
{
    private static function e(mixed $v): string { return View::esc($v); }

    private static function link(array $d): string
    {
        return '/admin/onboarding?domain='.rawurlencode((string)$d['domain_guid']).'#domain-review';
    }

    private static function isCanonical(array $d): bool
    {
        $configured=parse_url((string)($d['application_url']??''),PHP_URL_HOST);
        return is_string($configured) && $configured!=='' &&
            strcasecmp($configured,(string)$d['hostname'])===0;
    }

    private static function stage(array $d,array $progress): array
    {
        if (self::isCanonical($d)) return ['class'=>'system','label'=>'App host','hint'=>'Canonical ePaper application domain'];
        if (!empty($d['_shares_default'])) return ['class'=>'shared','label'=>'Shared tenant','hint'=>'Shares publication data with the canonical application hostname'];
        if ($progress['live']) return ['class'=>'live','label'=>'Approved','hint'=>'Go-live confirmed'];
        if (($d['status']??'')==='ACTIVE') return ['class'=>'review','label'=>'Active · review','hint'=>'Existing active domain, acceptance review outstanding'];
        if (in_array((string)($d['status']??''),['SUSPENDED','REVALIDATION_FAILED'],true))
            return ['class'=>'blocked','label'=>'Blocked','hint'=>'Domain suspended or DNS revalidation required'];
        if ($progress['stalled']) return ['class'=>'stalled','label'=>'Stalled','hint'=>'No recent change for 48 hours'];
        return ['class'=>'pending','label'=>'In setup','hint'=>'Publisher onboarding in progress'];
    }

    public static function render(
        array $domains,
        array $reviews,
        array $users,
        array $tenants,
        string $filter,
        string $q,
        string $detail
    ): string {
        $m=['domains'=>count($domains),'approved'=>0,'review'=>0,'pending'=>0,'system'=>0];
        $prepared=[];
        $canonicalTenantIds=[];
        foreach($domains as $candidate) {
            if(self::isCanonical($candidate)) $canonicalTenantIds[(string)$candidate['product_tenant_guid']]=true;
        }
        foreach($domains as $d) {
            $d['_shares_default']=!self::isCanonical($d)
                && isset($canonicalTenantIds[(string)$d['product_tenant_guid']]);
            $d['reviewed']=isset($reviews[$d['domain_guid']]);
            $progress=Onboarding::state($d);
            $stage=self::stage($d,$progress);
            $d['_progress']=$progress;
            $d['_stage']=$stage;
            if($stage['class']==='system') $m['system']++;
            elseif($stage['class']==='live') $m['approved']++;
            elseif(in_array($stage['class'],['review','shared'],true)) $m['review']++;
            else $m['pending']++;
            $prepared[]=$d;
        }

        $filtered=[];
        foreach($prepared as $d) {
            $stage=$d['_stage']['class'];
            if($q!=='' && stripos((string)$d['hostname'].' '.(string)$d['organisation_name'].' '.(string)$d['tenant_name'],$q)===false)continue;
            if($filter==='pending'&&!in_array($stage,['pending','blocked','stalled'],true))continue;
            if($filter==='review'&&!in_array($stage,['review','shared'],true))continue;
            if($filter==='live'&&$stage!=='live')continue;
            $filtered[]=$d;
        }

        $hero='<div class="ob-hero">'
            .'<div class="ob-hero-copy"><span class="ob-kicker"><span class="ob-kicker-dot"></span> PUBLISHER OPERATIONS</span>'
            .'<h2>Every publisher. One control centre.</h2>'
            .'<p>View existing domains, identify blockers and approve new publishers for launch — without a separate ePaper installation.</p>'
            .'</div><div class="ob-hero-actions"><a class="button primary" href="/admin/onboarding?mode=new#new-publisher">+ New publisher</a>'
            .'<a class="button" href="#existing-domains">View existing domains</a></div></div>';

        $stats='<div class="ob-overview" aria-label="Domain overview">'
            .self::metric('Registered domains',$m['domains'],'All ePaper hostnames','domains')
            .self::metric('Approved & live',$m['approved'],'Go-live audit recorded','approved')
            .self::metric('Needs review',$m['review'],'Active domains not yet reviewed','review')
            .self::metric('In setup / blocked',$m['pending'],'Outstanding onboarding','pending')
            .'</div>';

        $attention=self::attention($prepared);
        $domainTable=self::domainTable($filtered,$filter,$q,count($prepared));
        $publisherTable=self::publisherTable($tenants);
        $create=self::createPanel($users,$tenants);
        $foot='<p class="ob-footer-note">Statuses reflect current Platform records, not a live Cloudways deployment probe. <a href="/admin/domains">Open advanced domain registry</a>.</p>';

        return '<div class="ob-workbench">'.$hero.$stats.$detail.$attention.$domainTable.$publisherTable.$create.$foot.'</div>';
    }

    private static function metric(string $title,int $value,string $note,string $type): string
    {
        return '<article class="ob-overview-card ob-overview-'.self::e($type).'">'
            .'<span class="ob-overview-title">'.self::e($title).'</span>'
            .'<strong>'.self::e((string)$value).'</strong>'
            .'<span class="ob-overview-note">'.self::e($note).'</span></article>';
    }

    private static function attention(array $domains): string
    {
        $rows=[];
        foreach ($domains as $d) {
            $stage=$d['_stage'];
            if(in_array($stage['class'],['live','system'],true))continue;
            $p=$d['_progress'];
            $message=$stage['class']==='shared'
                ?'Same tenant as canonical ePaper host. Reassign if this publisher needs separate content.'
                :($stage['class']==='review'?'Verify live HTTPS, publisher access and tenant isolation':$p['blocker']);
            $rows[]='<li class="ob-task"><span class="ob-task-icon">!</span>'
                .'<div><strong>'.self::e($d['hostname']).'</strong><small>'.self::e($message).'</small></div>'
                .'<a href="'.self::link($d).'">Resolve</a></li>';
            if(count($rows)>=4)break;
        }
        return '<section class="ob-attention" aria-labelledby="ob-attention-heading"><div class="ob-attention-title">'
            .'<div><span class="ob-kicker">NEXT ACTIONS</span><h3 id="ob-attention-heading">Requires attention</h3></div>'
            .'<a href="#existing-domains">All domains</a></div>'
            .($rows?'<ul class="ob-task-list">'.implode('',$rows).'</ul>':'<div class="ob-empty-task">No publisher launch blockers currently listed.</div>')
            .'</section>';
    }

    private static function domainTable(array $domains,string $filter,string $q,int $total): string
    {
        $filters=['all'=>'All domains','pending'=>'In setup','review'=>'Needs review','live'=>'Approved'];
        $tabs='';
        foreach($filters as $key=>$value) {
            $tabs.='<a class="ob-filter'.($filter===$key?' current':'').'" href="/admin/onboarding?filter='.$key.'#existing-domains">'
                .self::e($value).'</a>';
        }
        $rows='';
        foreach($domains as $d) {
            $progress=$d['_progress'];
            $stage=$d['_stage'];
            $pct=(int)round($progress['done']/6*100);
            $next=$stage['class']==='live'?'No outstanding actions':
                ($stage['class']==='system'?'Canonical infrastructure domain':
                ($stage['class']==='shared'?'Check publisher tenant mapping':($stage['class']==='review'?'Confirm existing deployment':$progress['blocker'])));
            $updated=(string)($d['last_checked_at']??'');
            if($updated==='')$updated=(string)($d['updated_at']??'');
            $displayDate=$updated!==''?substr($updated,0,16):'—';
            $visit='https://'.(string)$d['hostname'].'/';
            $rows.='<tr>'
                .'<td data-label="Publisher"><strong class="ob-td-title">'.self::e($d['organisation_name']).'</strong><span class="ob-td-sub">'.self::e($d['tenant_name']).'</span></td>'
                .'<td data-label="Domain"><a class="ob-domain-link" href="'.self::e($visit).'" target="_blank" rel="noopener noreferrer">'.self::e($d['hostname']).'</a><span class="ob-td-sub">'.self::e((string)($d['application_name']??'EPAPER')).'</span></td>'
                .'<td data-label="Status"><span class="ob-status ob-status-'.self::e($stage['class']).'">'.self::e($stage['label']).'</span></td>'
                .'<td data-label="Progress"><div class="ob-table-progress"><span class="ob-mini-track"><span style="width:'.$pct.'%"></span></span><b>'.$progress['done'].' / 6</b></div></td>'
                .'<td data-label="Next step"><span class="ob-next-step">'.self::e($next).'</span></td>'
                .'<td data-label="Checked"><span class="ob-date">'.self::e($displayDate).'</span></td>'
                .'<td data-label="Action"><a class="ob-manage" href="'.self::link($d).'">Manage <span aria-hidden="true">›</span></a></td>'
                .'</tr>';
        }
        $search='<form action="/admin/onboarding" method="get" class="ob-domain-search">'
            .'<label for="ob-search" class="ob-sr-only">Search publisher domains</label>'
            .'<input id="ob-search" name="q" type="search" value="'.self::e($q).'" placeholder="Search publisher or domain">'
            .'<button type="submit">Search</button>'
            .($q!==''?'<a href="/admin/onboarding#existing-domains" class="button">Clear</a>':'')
            .'</form>';
        return '<section id="existing-domains" class="ob-panel ob-domain-panel">'
            .'<div class="ob-panel-heading"><div><span class="ob-kicker">EXISTING DOMAIN INVENTORY</span><h3>Registered ePaper domains</h3><p>All publisher hostnames currently registered in Digiti Platform, including existing ones.</p></div>'
            .'<span class="ob-count-label">'.count($domains).' shown · '.$total.' total</span></div>'
            .'<div class="ob-domain-tools"><nav class="ob-filter-tabs" aria-label="Filter domains">'.$tabs.'</nav>'.$search.'</div>'
            .'<div class="ob-data-scroll"><table class="ob-data-table"><thead><tr><th>Publisher</th><th>Domain</th><th>Status</th><th>Progress</th><th>Next step</th><th>Last update</th><th></th></tr></thead>'
            .'<tbody>'.($rows?:'<tr><td class="ob-empty-row" colspan="7">No matching domains. Clear the search or register a publisher below.</td></tr>').'</tbody></table></div>'
            .'</section>';
    }

    private static function publisherTable(array $tenants): string
    {
        $rows='';
        foreach($tenants as $t) {
            $count=(int)($t['domain_count']??0);
            $owners=(int)($t['owner_count']??0);
            $s=(string)($t['service_access_state']??'NOT CONFIGURED');
            $rows.='<tr><td data-label="Publisher"><strong class="ob-td-title">'.self::e($t['organisation_name']).'</strong>'
                .'<span class="ob-td-sub">'.self::e($t['display_name']).'</span></td>'
                .'<td data-label="Domains">'.self::e((string)$count).'</td>'
                .'<td data-label="Publisher admins">'.self::e((string)$owners).'</td>'
                .'<td data-label="Service access"><span class="ob-status '.($s==='FULL'?'ob-status-live':'ob-status-pending').'">'.self::e($s).'</span></td>'
                .'<td data-label="Manage"><a class="ob-manage" href="/admin/product-tenants/'.rawurlencode((string)$t['product_tenant_guid']).'/edit">Manage <span aria-hidden="true">›</span></a></td></tr>';
        }
        return '<section class="ob-panel ob-tenants-panel" id="existing-publishers">'
            .'<div class="ob-panel-heading"><div><span class="ob-kicker">CURRENT CUSTOMERS</span><h3>Existing publishers</h3><p>EPAPER tenant records, including publishers that do not have a domain yet.</p></div>'
            .'<a class="button" href="/admin/users">Manage users</a></div>'
            .'<div class="ob-data-scroll"><table class="ob-data-table ob-tenant-table">'
            .'<thead><tr><th>Organisation / Tenant</th><th>Domains</th><th>Publisher admins</th><th>Service access</th><th></th></tr></thead>'
            .'<tbody>'.($rows?:'<tr><td colspan="5" class="ob-empty-row">No EPAPER publishers registered yet.</td></tr>').'</tbody></table></div>'
            .'</section>';
    }

    private static function optionList(array $items,string $id,string $label,string $prefix=''): string
    {
        $options='';
        foreach ($items as $item) {
            $text=($prefix!==''?(string)($item[$prefix]??'').' · ':'').(string)($item[$label]??'');
            $options.='<option value="'.self::e($item[$id]).'">'.self::e($text).'</option>';
        }
        return $options;
    }

    private static function createPanel(array $users,array $tenants): string
    {
        $mode=(string)($_GET['mode']??'');
        $open=in_array($mode,['new','alias'],true)?' open':'';
        $userOptions='<option value="">Assign later</option>';
        foreach($users as $u)$userOptions.='<option value="'.self::e($u['user_guid']).'">'.self::e($u['display_name'].' · '.$u['email']).'</option>';
        $tenantOptions='<option value="">Select an existing EPAPER tenant</option>';
        foreach($tenants as $t)$tenantOptions.='<option value="'.self::e($t['product_tenant_guid']).'">'.self::e($t['organisation_name'].' · '.$t['display_name']).'</option>';
        $csrf='<input type="hidden" name="_csrf" value="'.self::e(\Digiti\Core\Http::csrf()).'">';
        $new='<form class="ob-setup-form" method="post" action="/admin/onboarding">'
            .'<h4>New publisher</h4><p>Create a separate organisation and ePaper tenant.</p>'.$csrf.'<input type="hidden" name="mode" value="new">'
            .'<label>Publisher name<input name="organisation_name" maxlength="190" required placeholder="Pune Mirror Media"></label>'
            .'<label>Publisher code<input name="organisation_code" maxlength="64" required placeholder="PUNE_MIRROR"></label>'
            .'<label>Domain<input name="hostname" required autocomplete="off" placeholder="epaper.punemirror.com"></label>'
            .'<label>Existing owner<select name="owner_user_guid">'.$userOptions.'</select></label>'
            .'<button class="primary" type="submit">Create publisher and domain</button></form>';
        $alias='<form class="ob-setup-form" method="post" action="/admin/onboarding">'
            .'<h4>Additional domain</h4><p>Reuse an existing publisher tenant and its content.</p>'.$csrf.'<input type="hidden" name="mode" value="existing">'
            .'<label>Publisher<select name="product_tenant_guid" required>'.$tenantOptions.'</select></label>'
            .'<label>Domain<input name="hostname" required autocomplete="off" placeholder="epaper.example.com"></label>'
            .'<label>Existing owner (optional)<select name="owner_user_guid">'.$userOptions.'</select></label>'
            .'<button type="submit">Attach domain</button></form>';
        return '<details id="new-publisher" class="ob-create-details"'.$open.'>'
            .'<summary><span class="ob-create-icon">+</span><span><strong>Add a publisher or domain</strong><small>Open setup only when onboarding a new customer</small></span><span class="ob-create-chevron" aria-hidden="true">⌄</span></summary>'
            .'<div class="ob-create-body"><div class="ob-setup-grid">'.$new.$alias.'</div></div></details>';
    }
}
