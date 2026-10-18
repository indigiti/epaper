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
    "proc_open","proc_terminate","STAGING_ONLY","MIGRATION_SOURCE_INVALID"
] as $token){
    if(!str_contains($code,$token))throw new RuntimeException('Missing setup gate '.$token);
}
foreach(["Http::verifyCsrf()","'OWNER','ADMIN'","AiClippingWebSetup::applyCandidateSchema",
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
    !str_contains($disabled,'Staging changes are locked')){
    throw new RuntimeException('Production UI must not expose migration submit control');
}
echo "Browser-only AI setup, staging isolation, CSRF, role and no-console contracts: OK\n";
