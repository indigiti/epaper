<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\{Store,View};

/** An operator-first home screen with real Platform records (no demo metrics). */
final class Home
{
    private static function e(mixed $value): string { return View::esc($value); }
    private static function count(string $sql): int { return (int)Store::value($sql); }
    private static function manage(string $guid): string { return '/admin/onboarding?domain='.rawurlencode($guid).'#domain-review'; }

    public static function index(): never
    {
        $organisations=self::count('SELECT COUNT(*) FROM organisations WHERE status="ACTIVE"');
        $team=self::count('SELECT COUNT(*) FROM platform_users WHERE status="ACTIVE"');
        $domains=self::count('SELECT COUNT(*) FROM domains d JOIN products p ON p.product_guid=d.product_guid WHERE p.product_key="EPAPER" AND d.status<>"REMOVED"');
        $publishers=self::count('SELECT COUNT(*) FROM product_tenants pt JOIN products p ON p.product_guid=pt.product_guid WHERE p.product_key="EPAPER" AND pt.status="ACTIVE"');
        $recent=Store::all('SELECT d.domain_guid,d.hostname,d.status,d.product_tenant_guid,d.updated_at,
            o.display_name organisation_name,pt.display_name tenant_name,a.base_url application_url
            FROM domains d JOIN products p ON p.product_guid=d.product_guid AND p.product_key="EPAPER"
            JOIN organisations o ON o.organisation_guid=d.organisation_guid
            JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid
            JOIN applications a ON a.application_guid=d.application_guid
            WHERE d.status<>"REMOVED" ORDER BY d.updated_at DESC LIMIT 80');
        $confirmed=[];
        foreach(Store::all('SELECT DISTINCT approved.resource_guid FROM audit_events approved
            WHERE approved.action="domain.onboarding.confirm" AND approved.resource_type="domain"
                AND approved.result="SUCCESS"
                AND NOT EXISTS (SELECT 1 FROM audit_events changed
                    WHERE changed.resource_type="domain" AND changed.resource_guid=approved.resource_guid
                        AND changed.occurred_at>=approved.occurred_at
                        AND changed.action IN ("domain.mapping.changed","domain.onboarding.reassign",
                            "domain.staging_reset","domain.remove","registry.restore"))
            ORDER BY approved.resource_guid LIMIT 500') as $event){
            $confirmed[(string)$event['resource_guid']]=true;
        }

        $canonicalTenant=[];
        foreach($recent as $item) {
            $canonical=(string)(parse_url((string)$item['application_url'],PHP_URL_HOST)?:'');
            if($canonical!==''&&strcasecmp($canonical,(string)$item['hostname'])===0)
                $canonicalTenant[(string)$item['product_tenant_guid']]=true;
        }
        $attention=[];
        $live=0;
        $shown=0;
        foreach($recent as $item) {
            $host=(string)$item['hostname'];
            $canonical=(string)(parse_url((string)$item['application_url'],PHP_URL_HOST)?:'');
            if($canonical!==''&&strcasecmp($canonical,$host)===0)continue;
            $shares=isset($canonicalTenant[(string)$item['product_tenant_guid']]);
            $approved=isset($confirmed[(string)$item['domain_guid']])&&$item['status']==='ACTIVE';
            if($approved&&!$shares){$live++;continue;}
            if($shares) {
                $label='Shares the main ePaper newspaper';
                $hint='Check this publisher is intentionally sharing content before approval.';
                $level='warning';
            } elseif(in_array($item['status'],['REVALIDATION_FAILED','SUSPENDED'],true)) {
                $label='Connection needs attention';$hint='Check domain verification and routing.';$level='danger';
            } elseif($item['status']==='ACTIVE') {
                $label='Needs final review';$hint='Check HTTPS, sign-in and private newspaper data.';$level='warning';
            } else {
                $label='Setup not complete';$hint='Open the checklist to see the remaining steps.';$level='pending';
            }
            $attention[]=[
                'hostname'=>$host,'publisher'=>(string)$item['organisation_name'],
                'label'=>$label,'hint'=>$hint,'level'=>$level,
                'url'=>self::manage((string)$item['domain_guid']),
            ];
            $shown++;
        }

        $hero='<section class="home-hero"><div><span class="home-eyebrow">DIGITI CONTROL CENTRE</span>'
            .'<h2>Manage your publishers in one place.</h2>'
            .'<p>Add a newspaper, connect its website, give the team access and check what is ready to launch.</p></div>'
            .'<div class="home-hero-actions"><a class="primary button" href="/admin/onboarding?mode=new#new-publisher">Add a publisher</a>'
            .'<a class="button" href="/admin/onboarding#existing-domains">View all domains</a></div></section>';

        $stats='<div class="home-stats">'
            .self::metric('Publishers',$publishers,'Independent EPAPER accounts')
            .self::metric('Domains',$domains,'Registered newspaper websites')
            .self::metric('Team members',$team,'Active user accounts')
            .self::metric('Organisations',$organisations,'Active customer organisations')
            .'</div>';

        $tasks='<section class="home-section"><div class="home-heading"><div><span class="home-eyebrow">COMMON TASKS</span><h3>What would you like to do?</h3></div></div>'
            .'<div class="home-actions">'
            .self::action('01','Add a publisher','Create a customer and their first ePaper domain together.','Start setup','/admin/onboarding?mode=new#new-publisher')
            .self::action('02','Fix a website','See why a domain is blocked or showing another newspaper.','Check domains','/admin/onboarding#existing-domains')
            .self::action('03','Add a team member','Create a user and assign access to the correct organisation.','Manage people','/admin/users')
            .self::action('04','Manage access & plans','See subscriptions and whether publishers have full service access.','Subscriptions','/admin/subscriptions')
            .'</div></section>';

        $attentionRows='';
        foreach(array_slice($attention,0,6) as $item) {
            $attentionRows.='<div class="home-issue"><div class="home-issue-indicator '.$item['level'].'" aria-hidden="true"></div>'
                .'<div class="home-issue-info"><strong>'.self::e($item['hostname']).'</strong><small>'.self::e($item['publisher']).' · '.self::e($item['hint']).'</small></div>'
                .'<span class="home-issue-label">'.self::e($item['label']).'</span>'
                .'<a href="'.self::e($item['url']).'" class="button">Fix issue</a></div>';
        }
        $attentionSection='<section class="home-section home-focus"><div class="home-heading">'
            .'<div><span class="home-eyebrow">ACTION REQUIRED</span><h3>Websites needing attention</h3>'
            .'<p>These results use current Platform records, not a live Cloudways test.</p></div>'
            .'<a href="/admin/onboarding" class="button">View all</a></div>'
            .($attentionRows?:'<div class="home-empty">No issues found in the recently updated ePaper domains. New setups still need final acceptance checks.</div>')
            .$attentionRows.'</section>';

        $guide='<section class="home-section home-guide"><div class="home-heading">'
            .'<div><span class="home-eyebrow">SIMPLE GUIDE</span><h3>How to launch a newspaper</h3></div>'
            .'<a href="/admin/help">Full instructions</a></div>'
            .'<div class="home-steps">'
            .self::step('1','Create','Enter newspaper name and domain.')
            .self::step('2','Connect','Complete DNS and Cloudways/SSL checks.')
            .self::step('3','Grant access','Assign a publisher owner and enable service access.')
            .self::step('4','Approve','Verify the correct newspaper and confirm go-live.')
            .'</div></section>';

        View::layout('Dashboard',$hero.$stats.$tasks.$attentionSection.$guide,[
            'subtitle'=>'A simpler way to manage customers, websites and access',
        ]);
    }

    private static function metric(string $title,int $value,string $detail): string
    {
        return '<div class="home-stat"><span>'.self::e($title).'</span><strong>'.self::e((string)$value).'</strong><small>'.self::e($detail).'</small></div>';
    }

    private static function action(string $num,string $title,string $description,string $cta,string $link):string
    {
        return '<a class="home-action" href="'.self::e($link).'"><span class="home-action-index">'.self::e($num).'</span><strong>'.self::e($title).'</strong>'
            .'<span class="home-action-description">'.self::e($description).'</span><span class="home-action-cta">'.self::e($cta).' →</span></a>';
    }

    private static function step(string $num,string $title,string $description): string
    {
        return '<div class="home-step"><span class="home-step-index">'.self::e($num).'</span><div><strong>'.self::e($title).'</strong><small>'.self::e($description).'</small></div></div>';
    }
}
