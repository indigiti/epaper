<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class) use($root):void{
    $prefix='EpaperSaas\\';
    if(!str_starts_with($class,$prefix))return;
    $path=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require $path;
});

$policy=\EpaperSaas\Services\AdminRoutePolicy::class;
$roles=['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','OPERATOR'];
$editorRoutes=[
 '/admin/editions','/admin/editions/01a14d4c-0ee8-71df-a18e-e714ad1d8bc8/publish',
 '/admin/manual-clipping','/admin/ai-clipping','/admin/ai-review',
 '/admin/navigation','/admin/publications','/admin/highlights','/admin/articles',
 '/admin/authors','/admin/topics','/admin/entities','/admin/master-data',
];
$managerRoutes=['/admin/subscribers','/admin/subscribers/01a14d4c-0ee8-71df-a18e-e714ad1d8bc8/status',
 '/admin/plans','/admin/settings','/admin/settings/brand','/admin/access',
 '/admin/audit','/admin/storage','/admin/system/release-audit'];
$operatorRoutes=['/admin','/admin/account','/admin/processing',
 '/admin/system/testing','/admin/system/health','/admin/errors','/admin/performance'];
foreach($editorRoutes as $route){
 if($policy::requiredCapability($route)!=='EDITOR')throw new RuntimeException("Missing editor restriction: $route");
 foreach($roles as $role){
  $expected=$role!=='OPERATOR';
  if($policy::allows($route,$role)!==$expected)throw new RuntimeException("Editor route ACL mismatch: $role $route");
 }
}
foreach($managerRoutes as $route){
 if($policy::requiredCapability($route)!=='MANAGER')throw new RuntimeException("Missing manager restriction: $route");
 foreach($roles as $role){
  $expected=in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN'],true);
  if($policy::allows($route,$role)!==$expected)throw new RuntimeException("Manager route ACL mismatch: $role $route");
 }
}
foreach($operatorRoutes as $route){
 foreach($roles as $role)if(!$policy::allows($route,$role))throw new RuntimeException("Allowed operator route denied: $role $route");
}
// Guard paths must match exact prefix components, not similarly named siblings.
foreach(['/admin/article','/admin/editionsXYZ','/admin/audit-export'] as $route)
 if($policy::requiredCapability($route)!=='ADMIN')throw new RuntimeException("Imprecise role prefix: $route");
foreach(['/admin/system/infrastructure','/admin/system/recovery','/admin/system/upgrade','/admin/performance/cache-action'] as $route)
 if($policy::requiredCapability($route)!=='ADMIN')
  throw new RuntimeException("Stronger infrastructure authorization path was changed: $route");
$app=(string)file_get_contents($root.'/app/App.php');
if(!str_contains($app,'AdminRoutePolicy::enforce($path)'))
 throw new RuntimeException('Admin route security policy not wired to dispatcher');
$catalog=\EpaperSaas\Services\AdminFeatureCatalog::class;
foreach([
 ['role'=>'OPERATOR','expected'=>['/admin/processing','/admin/errors']],
 ['role'=>'EDITOR','expected'=>['/admin/editions','/admin/articles']],
 ['role'=>'OWNER','expected'=>['/admin/audit','/admin/settings']],
] as $item){
 $links=array_column($catalog::flatten($catalog::groups($item['role'],true,false)),1);
 foreach($item['expected'] as $href){
  if(!in_array($href,$links,true)||!$policy::allows($href,$item['role']))
   throw new RuntimeException('Catalog and direct-link policy mismatch: '.$item['role'].' '.$href);
 }
}
echo "Admin role policies: route-level owner/editor/operator isolation OK\n";
