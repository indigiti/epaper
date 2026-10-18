<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void {
 $prefix='EpaperSaas\\';if(!str_starts_with($class,$prefix))return;
 $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
 if(is_file($file))require $file;
});
$catalog=\EpaperSaas\Services\AdminFeatureCatalog::class;
$routes=static fn(array $groups):array=>array_column($catalog::flatten($groups),1);
$owner=$catalog::groups('OWNER',true,true);
$editor=$catalog::groups('EDITOR',true,false);
$operator=$catalog::groups('OPERATOR',false,false);
$publisher=$catalog::groups('PUBLISHER_ADMIN',false,false);
$noAi=$catalog::groups('OWNER',false,true);
$customInfra=$catalog::groups('PUBLISHER_ADMIN',false,true);
foreach(['/admin','/admin/editions','/admin/processing','/admin/ai-review',
  '/admin/system/testing','/admin/system/infrastructure','/admin/access'] as $href){
 if(!in_array($href,$routes($owner),true))throw new RuntimeException('Owner navigation missing '.$href);
}
foreach(['/admin/system/infrastructure','/admin/access','/admin/plans','/admin/settings'] as $href){
 if(in_array($href,$routes($editor),true))throw new RuntimeException('Editor saw prohibited admin shortcut: '.$href);
 if(in_array($href,$routes($operator),true))throw new RuntimeException('Operator saw prohibited admin shortcut: '.$href);
}
if(in_array('/admin/editions',$routes($operator),true))throw new RuntimeException('Operator received edition creation tools');
if(in_array('/admin/ai-review',$routes($noAi),true))throw new RuntimeException('Disabled AI is still advertised');
if(!in_array('/admin/system/testing',$routes($operator),true))throw new RuntimeException('Operator lost QA');
if(in_array('/admin/system/infrastructure',$routes($publisher),true))throw new RuntimeException('Publisher received infrastructure shortcut');
if(!in_array('/admin/system/infrastructure',$routes($customInfra),true))throw new RuntimeException('Configured infrastructure role lost access to its own admin link');
$view=(string)file_get_contents($root.'/app/Core/View.php');
$app=(string)file_get_contents($root.'/app/App.php');
$css=(string)file_get_contents($root.'/public_html/assets/admin-workspace.css');
$js=(string)file_get_contents($root.'/public_html/assets/admin-workspace.js');
foreach(["Build::asset('/assets/admin-workspace.css')","Build::asset('/assets/admin-workspace.js')",
 "AdminFeatureCatalog::groups",'grouped-nav-compact','nav-section'] as $token)
 if(!str_contains($view,$token))throw new RuntimeException('Missing admin view integration '.$token);
foreach(['AdminWorkspace::render','AdminWorkspace::shortcuts','ORDER BY e.scheduled_at ASC LIMIT 4',
          'e.tenant_guid=?'] as $token)
 if(!str_contains($app,$token))throw new RuntimeException('Dashboard role/performance contract missing '.$token);
foreach(['grid-template-columns:repeat(3,minmax(0,1fr))','@media(max-width:540px)', '.launchpad-items',
          '.nav-section-title'] as $token)
 if(!str_contains($css,$token))throw new RuntimeException('Responsive admin CSS missing '.$token);
foreach(['data-admin-launchpad','localStorage','data-feature-pin','data-feature-item'] as $token)
 if(!str_contains($js,$token))throw new RuntimeException('Accessible catalog search/pin missing '.$token);
$workspace=\EpaperSaas\Services\AdminWorkspace::render($editor,'test-safe-scope');
if(!str_contains($workspace,'data-admin-launchpad') ||
   str_contains($workspace,'/admin/system/infrastructure') ||
   !str_contains($workspace,'/admin/editions'))
 throw new RuntimeException('Launcher role filtering is inconsistent with sidebar');
echo "Role-aware admin navigation, browser launcher and bounded query contracts: OK\n";
