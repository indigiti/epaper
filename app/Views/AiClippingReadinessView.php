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
        $canManage=(bool)($snapshot['setup_can_manage']??false);
        $result=(string)($_GET['setup_result']??'');
        $queue=is_array($snapshot['queue']??null)?$snapshot['queue']:[];
        $queueDriver=(string)($queue['driver']??'unknown');
        $rabbitActive=$queueDriver==='rabbitmq';
        $labels=[
            'EXISTING_STAGING_CONFIRMED'=>'This existing installation is confirmed for AI staging setup. No new environment was created and AI remains disabled.',
            'STAGING_CONFIRMATION_REQUIRED'=>'Verify the current hostname and database name, then confirm staging and backup.',
            'PRIVATE_RUNTIME_NOT_WRITABLE'=>'Private runtime configuration cannot be updated by the application. Check Cloudways application file permissions.',
            'PRIVATE_RUNTIME_INVALID'=>'Existing private runtime configuration is invalid; no changes were made.',
            'SCHEMA_CREATED'=>'Candidate table created in the current staging database.',
            'ALREADY_PRESENT'=>'Candidate table is already available; no changes were needed.',
            'PYTHON_SMOKE_PASSED'=>'Synthetic Python/OpenCV candidate test passed. You can now activate AI after RabbitMQ is healthy.',
            'AI_DETECTION_ENABLED'=>'AI Clipping flag enabled. Check Worker Doctor and process a test edition. Long-running OCR workers may require reload through Cloudways web management.',
            'AI_DETECTION_DISABLED'=>'AI Clipping disabled. Manual clipping and existing editions remain unchanged.',
            'ACTIVATION_CONFIRMATION_REQUIRED'=>'Please confirm editorial-only staging activation.',
            'CANDIDATE_SCHEMA_MISSING'=>'Candidate table is missing. Use the dashboard database setup first.',
            'AI_SOURCE_MISSING'=>'AI candidate source files are missing from the current deployment.',
            'RABBITMQ_NOT_CONFIGURED'=>'RabbitMQ is not the active queue driver. Check Worker Doctor.',
            'RABBITMQ_UNAVAILABLE'=>'RabbitMQ could not be reached. Check Processing Center.',
            'OCR_WORKER_NOT_RUNNING'=>'No active OCR worker heartbeat found. Check Worker Doctor.',
            'PYTHON_SMOKE_PASSED'=>'Synthetic Python and OpenCV candidate test passed.',
            'PYTHON_NOT_CONFIGURED'=>'No usable Python interpreter was found at the supported local paths. Python provisioning is required in Cloudways.',

            'WORKER_PROCESS_UNAVAILABLE'=>'The staging application cannot start the Python test.',
            'WORKER_TEST_TIMED_OUT'=>'Python validation timed out. No user data was touched.',
            'PYTHON_SMOKE_FAILED'=>'Python failed before the test could classify its result. Check Cloudways application process restrictions.',
            'PYTHON_DEPENDENCIES_INSTALLED'=>'NumPy and OpenCV are installed and passed the candidate test in private ePaper storage. You can now enable AI after RabbitMQ checks.',
            'INSTALL_CONFIRMATION_REQUIRED'=>'Confirm the staging-only dependency installation first.',
            'DISABLE_AI_BEFORE_INSTALL'=>'Disable AI detection before replacing Python dependencies.',
            'PYTHON_LIB_STORAGE_UNAVAILABLE'=>'Private storage cannot be prepared. Check Cloudways application storage permissions or space.',
            'PYTHON_INSTALL_ALREADY_RUNNING'=>'Another dependency installation is in progress. Do not submit again.',
            'PYTHON_INSTALL_TIMEOUT'=>'Installing the pinned wheels took longer than the allowed web request. NumPy and OpenCV remain unverified.',
            'PYTHON_PIP_MISSING'=>'Python pip is not installed or not available to the application. Cloudways must provision pip through hosting support.',
            'PYTHON_WHEEL_INSTALL_FAILED'=>'Python pip was found, but pinned wheel installation failed. Check Cloudways outbound HTTPS, free disk space and binary compatibility.',
            'PYTHON_WHEEL_VALIDATION_FAILED'=>'Wheel installation finished but the synthetic image detection failed. AI remains disabled.',
            'PYTHON_VERSION_UNSUPPORTED'=>'Available Python version is incompatible with the pinned image libraries. Python 3.9–3.12 is required.',
            'NUMPY_MISSING'=>'NumPy is not installed in the detected Python environment. The AI image-processing dependencies need provisioning.',
            'NUMPY_IMPORT_FAILED'=>'NumPy is installed but cannot load correctly. Its Python compatibility or native libraries need repair.',
            'OPENCV_MISSING'=>'OpenCV (cv2) is not installed in the detected Python environment. Image-region detection cannot run yet.',
            'OPENCV_IMPORT_FAILED'=>'OpenCV is installed but cannot load. A Python/NumPy/native-library incompatibility is likely.',
            'TEMP_STORAGE_UNAVAILABLE'=>'Python cannot create or read a private temporary test file. Check staging filesystem permissions or space.',
            'IMAGE_FIXTURE_FAILED'=>'OpenCV could not create the synthetic image. Check image library functionality and temporary storage.',
            'CANDIDATE_GENERATOR_FAILED'=>'The candidate generator failed on a synthetic newspaper page. Its application files or runtime need attention.',
            'CANDIDATE_CONTRACT_FAILED'=>'Python ran but returned an unexpected candidate layout. Check candidate-generation compatibility.',
            'CANDIDATE_TEST_TIMED_OUT'=>'The Python candidate test exceeded the hosting process time limit.',
            'CANDIDATE_PROCESS_UNAVAILABLE'=>'The hosting PHP process could not launch Python. Check Cloudways process restrictions.',

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
            .'<div class="ai-tile"><small>Environment</small><strong>'.($canStage?'Existing staging':'Not confirmed').'</strong>'
            .'<small>'.($canStage?'This installation is approved for staging setup; AI is not automatically activated.':'Confirm this existing installation as staging to unlock protected setup steps.').'</small></div></div>';
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
        $html.='<section class="ai-panel"><div class="ai-head"><h2>Processing queue readiness</h2>'
            .'<a class="btn ghost" href="/admin/system/infrastructure">Open RabbitMQ settings</a></div>'
            .'<div class="ai-checks">'
            .'<div class="ai-check"><div><strong>Active queue driver</strong><p>'
            .$e($rabbitActive?'RabbitMQ is selected. Verify actual consumers before processing OCR.':
                'The application is using '.strtoupper($queueDriver).'. This is not a Python error. Do not change drivers while jobs are running.')
            .'</p></div><span class="ai-tag">'.$e(strtoupper($queueDriver)).'</span></div>'
            .'<div class="ai-check"><div><strong>PHP AMQP client</strong><p>'
            .$e(($queue['amqp_client']??false)?'RabbitMQ client library is installed.':'RabbitMQ PHP client is not installed.')
            .'</p></div><span class="ai-tag">'.(!empty($queue['amqp_client'])?'PRESENT':'UNVERIFIED').'</span></div>'
            .'<div class="ai-check"><div><strong>Broker credentials</strong><p>'
            .$e(!empty($queue['credentials_present'])?'Credentials are configured; connectivity has not been tested by this display.':
                'RabbitMQ host, user or password is missing. Configure them in Infrastructure.')
            .'</p></div><span class="ai-tag">'.(!empty($queue['credentials_present'])?'SET':'MISSING').'</span></div>'
            .'<div class="ai-check"><div><strong>OCR heartbeat</strong><p>Recent OCR worker heartbeat signals, not a verified RabbitMQ consumer count.</p></div><span class="ai-tag">'
            .max(0,(int)($queue['ocr_heartbeat']??0)).' SEEN</span></div></div>'
            .'<p class="ai-muted">First use <strong>Test connection</strong> in Infrastructure without saving a driver change. '
            .'Only after a successful broker test, worker provisioning and safe queue cutover should you select RabbitMQ and save. '
            .'Then use Worker Doctor and the end-to-end worker pipeline test before enabling AI.</p>'
            .'<div class="ai-actions"><a class="btn ghost" href="/admin/system/infrastructure">Test RabbitMQ connection</a>'
            .'<a class="btn ghost" href="/admin/system/infrastructure/supervisor">Worker Doctor</a></div>'
            .'</section>';
        $html.='<section class="ai-panel"><h2>AI Clipping setup</h2>';
        if(!$canStage){
            $html.='<p><strong>Existing Cloudways installation — staging confirmation required.</strong></p>'
                .'<p class="ai-muted">You do not need another Cloudways application. Confirm that this current installation is your staging environment. '
                .'The hostname alone cannot establish staging status. No SQL, Python jobs or feature activation runs during confirmation.</p>';
            if($canManage){
                $csrf=$e(Http::csrf());
                $hostname=(string)(parse_url((string)Config::get('app.url',''),PHP_URL_HOST)?:'');
                $db=(string)Config::get('db.name','');
                $html.='<form class="ai-form" method="post" action="/admin/ai-clipping/action">'
                    .'<input type="hidden" name="_csrf" value="'.$csrf.'">'
                    .'<input type="hidden" name="action" value="confirm_existing_staging">'
                    .'<label for="ai-current-host">Confirm current staging hostname</label>'
                    .'<input id="ai-current-host" type="text" name="hostname" autocomplete="off" required placeholder="'.$e($hostname).'">'
                    .'<label for="ai-current-db">Confirm current ePaper database name</label>'
                    .'<input id="ai-current-db" type="text" name="database_name" autocomplete="off" required placeholder="'.$e($db).'">'
                    .'<label class="ai-confirm"><input type="checkbox" name="staging_confirmed" value="yes" required>'
                    .'<span>I confirm this existing installation contains staging/test data and may be used for AI setup. It is not serving production customer operations.</span></label>'
                    .'<label class="ai-confirm"><input type="checkbox" name="backup_confirmed" value="yes" required>'
                    .'<span>I confirm a backup and recovery plan for this installation are available.</span></label>'
                    .'<button type="submit" class="btn primary">Confirm existing staging installation</button></form>';
            }else{
                $html.='<p class="ai-muted">An OWNER or ADMIN must confirm staging status. Your account has read-only access.</p>';
            }
            $html.='<div class="ai-actions"><a class="btn ghost" href="/admin/system/health">System health</a>'
                .'<a class="btn ghost" href="/admin/system/infrastructure/supervisor">Worker Doctor</a></div>';
        }else{
            $html.='<p class="ai-muted">This existing installation is confirmed for AI staging setup. Its configured Platform tenant, domains and public Reader remain unchanged.</p>';
            if($canManage){
            $csrf=$e(Http::csrf());
            $html.='<p class="ai-muted">These actions run inside the current staging application. They do not publish articles or affect the public reader.</p>'
                .'<p class="ai-muted">The Python test identifies missing dependencies, native-library problems, temporary-storage errors and generator failures. It does not install packages or expose server paths.</p>'
                .'<div class="ai-actions"><form class="ai-form" method="post" action="/admin/ai-clipping/action">'
                .'<input type="hidden" name="_csrf" value="'.$csrf.'">'
                .'<input type="hidden" name="action" value="test_python">'
                .'<button type="submit" class="btn ghost">Test Python and image detection</button></form></div>';
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
            $html.='<div class="ai-panel" style="margin-top:16px"><h3>Python image dependencies</h3>'
                .'<p class="ai-muted">Install fixed NumPy 1.26.4 and OpenCV headless 4.10.0 into private ePaper storage. No global system packages are changed. This needs Python pip, HTTPS access to PyPI and writable private storage; it may take longer than a normal health check.</p>';
            if(!$enabled){
                $html.='<form class="ai-form" method="post" action="/admin/ai-clipping/action">'
                    .'<input type="hidden" name="_csrf" value="'.$csrf.'">'
                    .'<input type="hidden" name="action" value="install_python_deps">'
                    .'<label class="ai-confirm"><input type="checkbox" name="install_confirmed" value="yes" required>'
                    .'<span>Install the two pinned image-processing dependencies into this existing staging application only.</span></label>'
                    .'<button class="btn ghost" type="submit">Install image dependencies</button></form>';
            }else{
                $html.='<p class="ai-muted">Disable AI detection before changing Python dependencies.</p>';
            }
            $html.='</div><div class="ai-panel" style="margin-top:16px"><h3>AI detection activation</h3>';
            if($enabled){
                $html.='<p class="ai-muted">AI detection is enabled for OCR jobs after workers load the updated runtime. No automatic publishing is performed.</p>'
                    .'<form class="ai-form" method="post" action="/admin/ai-clipping/action">'
                    .'<input type="hidden" name="_csrf" value="'.$csrf.'">'
                    .'<input type="hidden" name="action" value="disable_ai">'
                    .'<button class="btn ghost" type="submit">Disable AI detection</button></form>';
            }elseif($schema==='AVAILABLE'&&!$rabbitActive){
                $html.='<p class="ai-muted"><strong>RabbitMQ is not active yet.</strong> Use the Infrastructure dashboard to test its connection and verify workers first. The AI switch stays locked until RabbitMQ is selected.</p>'
                    .'<a class="btn ghost" href="/admin/system/infrastructure">Open RabbitMQ setup</a>';
            }elseif($schema==='AVAILABLE'){
                $html.='<p class="ai-muted">Enable editorial-only region proposals. This action validates Python/OpenCV, RabbitMQ and the OCR worker; it does not publish articles. If a check fails, AI stays disabled.</p>'
                    .'<form class="ai-form" method="post" action="/admin/ai-clipping/action">'
                    .'<input type="hidden" name="_csrf" value="'.$csrf.'">'
                    .'<input type="hidden" name="action" value="enable_ai">'
                    .'<label class="ai-confirm"><input type="checkbox" name="activation_confirmed" value="yes" required>'
                    .'<span>Enable AI candidates on this staging installation only. Every proposed news region must still be reviewed before publication.</span></label>'
                    .'<button class="btn primary" type="submit">Enable AI detection</button></form>';
            }else{
                $html.='<p class="ai-muted">Install the candidate database table before enabling AI detection.</p>';
            }
            $html.='</div>';
            }else{
                $html.='<p class="ai-muted">Database and AI setup actions require OWNER or ADMIN permissions.</p>';
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
            .'<p class="ai-muted">Continue on this existing Cloudways staging installation. No new staging application is required. '
            .'Use the built-in Worker Doctor and Processing Center for RabbitMQ diagnostics. '
            .'Activate AI only after a disposable edition passes full OCR and tenant-security acceptance.</p>'
            .'<div class="ai-actions"><a class="btn ghost" href="/admin/system/infrastructure/supervisor">Worker Doctor</a>'
            .'<a class="btn ghost" href="/admin/system/testing">Run safe QA checks</a></div></section></section>';
        return $html;
    }
}
