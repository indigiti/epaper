<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='EpaperSaas\\';
    if(!str_starts_with($class,$prefix))return;
    $path=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require $path;
});
$catalog=\EpaperSaas\Services\AdminFeatureCatalog::class;
$paths=static fn(array $a):array=>array_column($catalog::flatten($a),1);
foreach(['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR'] as $role){
    if(!in_array('/admin/ai-clipping',$paths($catalog::groups($role,false,false)),true)){
        throw new RuntimeException('AI diagnostic link hidden for '.$role);
    }
}
if(in_array('/admin/ai-clipping',$paths($catalog::groups('OPERATOR',false,false)),true)){
    throw new RuntimeException('Operator received AI editorial diagnostic link');
}
if(in_array('/admin/ai-review',$paths($catalog::groups('OWNER',false,false)),true)){
    throw new RuntimeException('Disabled AI review was exposed');
}
$app=(string)file_get_contents($root.'/app/App.php');
$controller=(string)file_get_contents($root.'/app/Controllers/AdminAiClippingController.php');
$repository=(string)file_get_contents($root.'/app/Repositories/SqlAiClippingReadinessRepository.php');
$service=(string)file_get_contents($root.'/app/Services/AiClippingReadiness.php');
if(!str_contains($app,"if(\$path==='/admin/ai-clipping'&&\$method==='GET')")){
    throw new RuntimeException('GET-only route missing');
}
foreach(['Auth::requireAdmin()','Http::privateNoStore()','OWNER','EDITOR'] as $token){
    if(!str_contains($controller,$token))throw new RuntimeException('Controller guard missing: '.$token);
}
foreach(['information_schema.TABLES','WHERE tenant_guid=?','Uuid::toBinary($tenantGuid)'] as $token){
    if(!str_contains($repository,$token))throw new RuntimeException('Tenant-bound storage check missing: '.$token);
}
if(!str_contains($service,'AiCandidatePipeline::enabled()')){
    throw new RuntimeException('Runtime flag is not authoritative');
}
$view=\EpaperSaas\Views\AiClippingReadinessView::render([
    'enabled'=>false,'status'=>'DISABLED',
    'checks'=>[['name'=>'<script>alert(1)</script>','status'=>'MISSING','detail'=>'<img onerror=alert(2)>']],
    'counts'=>[],
    'review_url'=>null,
]);
if(str_contains($view,'<script>')||str_contains($view,'<img onerror=')||
   str_contains($view,'href="/admin/ai-review"')||!str_contains($view,'AI Clipping')){
    throw new RuntimeException('Readiness view XSS/disabled-link regression');
}
echo "AI clipping multilayer route, role, tenant and safe presentation: OK\n";
