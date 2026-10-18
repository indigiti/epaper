<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='EpaperSaas\\';
    if(!str_starts_with($class,$prefix))return;
    $path=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require $path;
});
$code=(string)file_get_contents($root.'/app/Services/AiClippingWebSetup.php');
$controller=(string)file_get_contents($root.'/app/Controllers/AdminAiClippingController.php');
$app=(string)file_get_contents($root.'/app/App.php');
$view=(string)file_get_contents($root.'/app/Views/AiClippingReadinessView.php');
foreach([
    "'staging'","'epaper.digiti.in'","'platform.default_tenant_guid'",
    "'platform.default_organisation_guid'","SELECT DATABASE()",
    "hash_equals(\$name,trim(\$typedName))","CREATE TABLE IF NOT EXISTS ep_ai_page_candidates",
    "STAGING_ONLY","MIGRATION_SOURCE_INVALID",
    "confirmExistingStaging","hash_equals(\$host,strtolower(trim(\$typedHost)))",
    "hash_equals(\$dbName,trim(\$typedDb))","existing_staging_confirmed",
    "PRIVATE_RUNTIME_NOT_WRITABLE","staging_declared"
] as $token){
    if(!str_contains($code,$token))throw new RuntimeException('Missing setup gate '.$token);
}
foreach(["Http::verifyCsrf()","'OWNER','ADMIN'","AiClippingWebSetup::confirmExistingStaging",
         "AiClippingActivation::enable","AiClippingActivation::disable",
         "AiClippingWebSetup::applyCandidateSchema",
         "AiClippingWebSetup::testPython"] as $token){
    if(!str_contains($controller,$token))throw new RuntimeException('Missing POST guard '.$token);
}
if(!str_contains($app,"/admin/ai-clipping/action")||!str_contains($view,'name="_csrf"')){
    throw new RuntimeException('Browser route/form missing');
}
if(str_contains($view,'<code>php ')||str_contains($view,'bin/ai-clipping-staging.php')){
    throw new RuntimeException('Admin UI must not send users to a console');
}
$disabled=\EpaperSaas\Views\AiClippingReadinessView::render([
    'enabled'=>false,'status'=>'DISABLED','checks'=>[],'counts'=>[],
    'review_url'=>null,'setup'=>['allowed'=>false,'staging'=>false,'host_safe'=>false,
        'platform_isolated'=>false,'database_match'=>false]
]);
if(str_contains($disabled,'name="action" value="apply_schema"')||
    str_contains($disabled,'name="action" value="confirm_existing_staging"')||
    !str_contains($disabled,'staging confirmation required')){
    throw new RuntimeException('Production UI must not expose migration submit control');
}
$owner=\EpaperSaas\Views\AiClippingReadinessView::render([
    'enabled'=>false,'status'=>'DISABLED','checks'=>[],'counts'=>[],
    'review_url'=>null,'setup_can_manage'=>true,
    'setup'=>['allowed'=>false,'database_match'=>true]
]);
if(!str_contains($owner,'name="action" value="confirm_existing_staging"')||
    str_contains($owner,'name="action" value="apply_schema"')){
    throw new RuntimeException('Existing staging confirmation must be separate from schema action');
}
$activation=(string)file_get_contents($root.'/app/Services/AiClippingActivation.php');
$installer=(string)file_get_contents($root.'/app/Services/AiClippingDependencies.php');
foreach(['AiClippingWebSetup::gate','numpy==1.26.4','opencv-python-headless==4.10.0.84',
    'pypi.org/simple','--only-binary=:all:','--no-deps','--no-input',
    'PYTHONPATH','privateRoot','LOCK_EX','proc_open','PYTHON_PIP_MISSING'] as $token){
    if(!str_contains($installer,$token))throw new RuntimeException('Private wheel installer guard missing '.$token);
}
if(!str_contains($controller,"AiClippingDependencies::install")||
   !str_contains($view,'name="action" value="install_python_deps"')){
    throw new RuntimeException('Admin dependency install form/action missing');
}
$bridge=(string)file_get_contents($root.'/app/Services/AiCandidatePipeline.php');
if(!str_contains($bridge,'AiClippingDependencies::environment()')||
   !str_contains($bridge,'AiClippingActivation::currentPythonBinary()')){
    throw new RuntimeException('OCR worker does not use private dependencies');
}
foreach(['public static function enable','public static function disable','AiClippingWebSetup::gate',
        "InfrastructureConfig::workerStatus","RabbitMq::instance()->ping",
        "python_validation","enabled'=>true","enabled'=>false","proc_open","proc_terminate"] as $token){
    if(!str_contains($activation,$token))throw new RuntimeException('Activation guard missing '.$token);
}
$ready=\EpaperSaas\Views\AiClippingReadinessView::render([
    'enabled'=>false,'status'=>'DISABLED','checks'=>[
        ['name'=>'Feature flag','status'=>'DISABLED'],
        ['name'=>'Candidate database','status'=>'AVAILABLE']],
    'counts'=>[],'review_url'=>null,'setup_can_manage'=>true,
    'setup'=>['allowed'=>true,'database_match'=>true]
]);
if(!str_contains($ready,'name="action" value="enable_ai"')||
   !str_contains($ready,'name="activation_confirmed"')){
    throw new RuntimeException('Owner cannot enable from existing staging UI');
}
$probeSource=(string)file_get_contents($root.'/bin/ai-clipping-probe.py');
foreach(['NUMPY_MISSING','OPENCV_MISSING','NUMPY_IMPORT_FAILED','OPENCV_IMPORT_FAILED',
        'TEMP_STORAGE_UNAVAILABLE','CANDIDATE_GENERATOR_FAILED','EPDI_AI_PROBE:'] as $token){
    if(!str_contains($probeSource,$token))throw new RuntimeException('Python diagnostic missing '.$token);
}
foreach(['NUMPY_MISSING','OPENCV_MISSING','CANDIDATE_CONTRACT_FAILED','CANDIDATE_TEST_TIMED_OUT'] as $token){
    if(!str_contains($controller,$token)||!str_contains($view,$token))
        throw new RuntimeException('Sanitized Python diagnostic not linked '.$token);
}
echo "Existing staging browser AI setup/activation and classified diagnostics: OK\n";
