<?php
declare(strict_types=1);
namespace EpaperSaas\Views;

use EpaperSaas\Core\{Config,Http,View};

/** Safe presentation only; runtime checks and database writes stay in separate layers. */
final class AiClippingReadinessView
{
    public static function render(array $snapshot): string
    {
        $e=static fn(mixed $v):string=>View::e($v);
        $enabled=(bool)($snapshot['enabled']??false);
        $status=(string)($snapshot['status']??'UNKNOWN');
        $setup=is_array($snapshot['setup']??null)?$snapshot['setup']:[];
        $canStage=(bool)($setup['allowed']??false);
        $result=(string)($_GET['setup_result']??'');
        $labels=[
            'SCHEMA_CREATED'=>'Candidate table created in isolated staging.',
            'ALREADY_PRESENT'=>'Candidate table is already available; no changes were needed.',
            'PYTHON_SMOKE_PASSED'=>'Synthetic Python and OpenCV candidate test passed.',
            'PYTHON_NOT_CONFIGURED'=>'The staging Python interpreter is not yet configured.',
            'WORKER_PROCESS_UNAVAILABLE'=>'The staging application cannot start the Python test.',
            'WORKER_TEST_TIMED_OUT'=>'Python validation timed out. No user data was touched.',
            'PYTHON_SMOKE_FAILED'=>'Synthetic Python test failed. Review the hosting worker configuration.',
            'STAGING_ONLY_OR_CONFIRMATION_REQUIRED'=>'Staging database or backup confirmation did not match.',
            'STAGING_ONLY'=>'This action is restricted to isolated staging.',
            'MIGRATION_SOURCE_INVALID'=>'The known candidate migration was not verified; no SQL applied.',
            'MIGRATION_VERIFICATION_FAILED'=>'Staging migration requires investigation before activation.',
            'SETUP_CHECK_FAILED'=>'The setup check did not complete; review system health.',
        ];
        $summary=match($status){
            'DISABLED'=>'AI candidate detection is disabled. Manual clipping remains available.',
            'ACTION_REQUIRED'=>'One or more candidate-processing prerequisites are missing.',
            'VALIDATION_REQUIRED'=>'Basic prerequisites are present. Real OCR and role-isolation acceptance remain required.',
            default=>'The current AI clipping activation state could not be determined.',
        };
        $html='<style>'
            .'.ai-console{display:grid;gap:16px}.ai-console .ai-tiles{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}'
            .'.ai-console .ai-tile,.ai-console .ai-panel{border:1px solid var(--border,#dce2e8);border-radius:14px;background:var(--surface,#fff);padding:20px}'
            .'.ai-console .ai-tile strong{display:block;font-size:15px;margin:9px 0}.ai-console .ai-tile small{display:block;color:#64748b;line-height:1.5}'
            .'.ai-console .ai-checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}'
            .'.ai-console .ai-check{padding:15px;border:1px solid #d9e1ea;border-radius:11px;display:flex;align-items:flex-start;justify-content:space-between;gap:15px}'
            .'.ai-console .ai-check p{color:#64748b;font-size:13px;line-height:1.55;margin:5px 0 0}'
            .'.ai-console .ai-tag{border-radius:100px;padding:5px 9px;background:#edf2f9;color:#334155;font-size:11px;font-weight:700;white-space:nowrap}'
            .'.ai-console .ai-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}'
            .'.ai-console .ai-actions{display:flex;flex-wrap:wrap;gap:10px;margin:15px 0}.ai-console .ai-muted{color:#64748b;line-height:1.55}'
            .'.ai-console .ai-form label{display:block;font-size:13px;font-weight:600;margin:10px 0 5px}'
            .'.ai-console .ai-form input[type=text]{display:block;width:min(100%,420px);padding:11px;border-radius:9px;border:1px solid #a9b7c8;background:transparent}'
            .'.ai-console .ai-form .ai-confirm{display:flex;gap:9px;align-items:flex-start;max-width:640px;font-weight:400}'
            .'.ai-console .ai-form button{margin-top:14px}.ai-console h2{font-size:19px;margin:0 0 12px}'
            .'.ai-console .ai-notice{padding:13px 16px;border:1px solid #becbda;border-radius:12px;background:#f3f7fc}'
            .'@media(max-width:760px){.ai-console .ai-tiles,.ai-console .ai-checks{grid-template-columns:1fr}}'
            .'</style><section class="admin-section ai-console">'
            .'<header class="ai-head"><div><span class="eyebrow">EDITORIAL WORKSPACE</span>'
            .'<h1>AI Clipping</h1><p class="ai-muted">Manage readiness in the admin dashboard—no server console required for these checks.</p></div>'
            .'<a class="btn ghost" href="/admin/processing">Processing Center</a></header>';
        if(isset($labels[$result]))$html.='<div role="status" class="ai-notice">'.$e($labels[$result]).'</div>';
        $html.='<div class="ai-tiles">'
            .'<div class="ai-tile"><small>AI detection</small><strong>'.$e(str_replace('_',' ',$status)).'</strong><small>'.$e($summary).'</small></div>'
            .'<div class="ai-tile"><small>Pending editorial candidates</small><strong>'.number_format(max(0,(int)($snapshot['counts']['PENDING']??0))).'</strong><small>Tenant-specific. Never automatically published.</small></div>'
            .'<div class="ai-tile"><small>Environment</small><strong>'.($canStage?'Isolated staging':'Protected').'</strong>'
            .'<small>'.($canStage?'Staging-only setup actions are available.':'Database and worker activation controls stay locked here.').'</small></div></div>';
        $html.='<section class="ai-panel"><div class="ai-head"><h2>Readiness checks</h2>'
            .'<a class="btn ghost" href="/admin/ai-clipping?refresh=1">Refresh checks</a></div>'
            .'<div class="ai-checks">';
        foreach(($snapshot['checks']??[]) as $check){
            if(!is_array($check))continue;
            $html.='<div class="ai-check"><div><strong>'.$e($check['name']??'Check').'</strong>'
                .'<p>'.$e($check['detail']??'').'</p></div><span class="ai-tag">'
                .$e(str_replace('_',' ',(string)($check['status']??'UNKNOWN'))).'</span></div>';
        }
        $html.='</div></section>';
        $html.='<section class="ai-panel"><h2>AI Clipping setup</h2>';
        if(!$canStage){
            $html.='<p><strong>Staging changes are locked.</strong></p>'
                .'<p class="ai-muted">This application has not passed the isolated staging identity checks. '
                .'For safety, candidate database migrations and worker tests are not available on the live ePaper tenant. '
                .'Create or select an isolated Cloudways staging application using its web dashboard, and return to AI Clipping there.</p>'
                .'<div class="ai-actions"><a class="btn ghost" href="/admin/system/health">System health</a>'
                .'<a class="btn ghost" href="/admin/system/infrastructure/supervisor">Worker Doctor</a></div>';
        }else{
            $csrf=$e(Http::csrf());
            $html.='<p class="ai-muted">These actions run inside the staging application. They do not publish articles or affect the public reader.</p>'
                .'<form class="ai-form" method="post" action="/admin/ai-clipping/action">'
                .'<input type="hidden" name="_csrf" value="'.$csrf.'">'
                .'<input type="hidden" name="action" value="test_python">'
                .'<button type="submit" class="btn ghost">Test Python and image detection</button></form>';
            $schema=(string)($snapshot['checks'][1]['status']??'UNAVAILABLE');
            if($schema==='MISSING'){
                $db=(string)Config::get('db.name','');
                $html.='<form class="ai-form" method="post" action="/admin/ai-clipping/action">'
                    .'<input type="hidden" name="_csrf" value="'.$csrf.'">'
                    .'<input type="hidden" name="action" value="apply_schema">'
                    .'<h3>Install the candidate database table</h3>'
                    .'<p class="ai-muted">Adds the isolated AI candidate table only. Existing editorial, edition and clipping data are unchanged.</p>'
                    .'<label for="ai-stage-db">Type the isolated staging database name to confirm</label>'
                    .'<input id="ai-stage-db" type="text" name="database_name" required autocomplete="off" placeholder="'.$e($db).'">'
                    .'<label class="ai-confirm"><input type="checkbox" name="backup_confirmed" value="yes" required>'
                    .'<span>I confirm that this is an isolated staging database and its backup and restore have been verified.</span></label>'
                    .'<button class="btn primary" type="submit">Create candidate table in staging</button></form>';
            }elseif($schema==='AVAILABLE'){
                $html.='<p class="ai-muted">Candidate database is already installed; no migration needed.</p>';
            }else{
                $html.='<p class="ai-muted">Database status is unavailable. Check System Health before changing it.</p>';
            }
        }
        $html.='</section><section class="ai-panel"><h2>Editorial review</h2>'
            .'<p class="ai-muted">Ready for review: '.number_format(max(0,(int)($snapshot['counts']['PENDING']??0)))
            .' · Reviewed: '.number_format(max(0,(int)($snapshot['counts']['REVIEWED']??0)))
            .' · Rejected: '.number_format(max(0,(int)($snapshot['counts']['REJECTED']??0))).'</p>';
        if($enabled&&($snapshot['review_url']??null)==='/admin/ai-review'){
            $html.='<a class="btn primary" href="/admin/ai-review">Open AI Editorial Review</a>';
        }else{
            $html.='<p class="ai-muted">Editorial review becomes available after the private feature flag, database and worker acceptance are complete.</p>';
        }
        $html.='</section><section class="ai-panel"><h2>Next readiness gates</h2>'
            .'<p class="ai-muted">Use Cloudways web management for staging isolation and runtime provisioning. '
            .'Use the built-in Worker Doctor and Processing Center for RabbitMQ diagnostics. '
            .'Activate AI only after a disposable edition passes full OCR and tenant-security acceptance.</p>'
            .'<div class="ai-actions"><a class="btn ghost" href="/admin/system/infrastructure/supervisor">Worker Doctor</a>'
            .'<a class="btn ghost" href="/admin/system/testing">Run safe QA checks</a></div></section></section>';
        return $html;
    }
}
