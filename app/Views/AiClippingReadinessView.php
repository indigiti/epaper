<?php
declare(strict_types=1);
namespace EpaperSaas\Views;

use EpaperSaas\Core\View;

/** HTML-only presentation. Receives diagnostics; never touches DB or workers. */
final class AiClippingReadinessView
{
    public static function render(array $snapshot): string
    {
        $e=static fn(mixed $v):string=>View::e($v);
        $enabled=(bool)($snapshot['enabled']??false);
        $status=(string)($snapshot['status']??'UNKNOWN');
        $summary=match($status){
            'DISABLED'=>'AI candidate detection is disabled in private runtime configuration. Existing manual clipping remains available.',
            'ACTION_REQUIRED'=>'Required AI source files or candidate storage are missing. Review the checks below before staging activation.',
            'VALIDATION_REQUIRED'=>'Basic source and schema prerequisites are visible. Live worker, OCR quality and tenant-isolation acceptance remain unverified.',
            default=>'The current AI clipping activation state could not be determined.',
        };
        $html='<section class="admin-section"><div class="section-head"><div><span class="eyebrow">EDITORIAL WORKSPACE</span>'
            .'<h1>AI Clipping</h1><p class="muted">Read-only activation and deployment diagnostics for the private editorial candidate workflow.</p>'
            .'</div><a class="btn ghost" href="/admin/processing">Processing Center</a></div>'
            .'<div class="panel"><h2>Current state: '.$e(str_replace('_',' ',$status)).'</h2><p>'.$e($summary).'</p>'
            .'<p class="muted">Automatic published articles and reader-facing AI clipping are not enabled by this screen.</p></div>'
            .'<div class="panel"><h2>Layer checks</h2><div class="data-list">';
        foreach(($snapshot['checks']??[]) as $check){
            if(!is_array($check))continue;
            $html.='<div class="data-row"><div><strong>'.$e($check['name']??'Unknown check').'</strong>'
                .'<p class="muted">'.$e($check['detail']??'').'</p></div>'
                .'<span class="role-pill">'.$e(str_replace('_',' ',(string)($check['status']??'UNKNOWN'))).'</span></div>';
        }
        $html.='</div></div><div class="panel"><h2>Editorial candidates for this tenant</h2>';
        $counts=is_array($snapshot['counts']??null)?$snapshot['counts']:[];
        foreach(['PENDING'=>'Awaiting review','REVIEWED'=>'Reviewed','REJECTED'=>'Rejected'] as $key=>$label){
            $html.='<p><strong>'.$e($label).':</strong> '.number_format(max(0,(int)($counts[$key]??0))).'</p>';
        }
        $url=$snapshot['review_url']??null;
        if($enabled&&$url==='/admin/ai-review'){
            $html.='<a class="btn primary" href="/admin/ai-review">Open AI Editorial Review</a>';
        }else{
            $html.='<p class="muted">AI Editorial Review is not accessible until the feature and candidate storage are configured.</p>';
        }
        $html.='</div><div class="panel"><h2>Staging activation sequence</h2>'
            .'<ol class="setup-steps"><li>Confirm the isolated Cloudways staging backup and rollback plan.</li>'
            .'<li>Apply the optional database/upgrade-ai-page-candidates-20261018.sql migration to the staging database only.</li>'
            .'<li>Provision private Python 3.12 and pinned image dependencies for the supervised OCR worker.</li>'
            .'<li>Enable ai_clipping.enabled in private staging runtime config, then restart staging workers.</li>'
            .'<li>Process a disposable edition through RabbitMQ PAGE_OCR and verify a pending candidate.</li>'
            .'<li>Verify editorial permissions, cross-tenant denial, manual clipping and reader regression before any production rollout.</li></ol>'
            .'<p><a href="/admin/system/testing">Run safe staging checks</a> · '
            .'<a href="/admin/system/health">System health</a></p></div></section>';
        return $html;
    }
}
