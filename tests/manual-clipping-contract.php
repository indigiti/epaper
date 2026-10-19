<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
 $prefix='EpaperSaas\\';if(!str_starts_with($class,$prefix))return;
 $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
 if(is_file($file))require $file;
});
$geom=\EpaperSaas\Services\ManualClipGeometry::class;
$good=$geom::parse(['x'=>'0.10','y'=>'0.2','w'=>'0.4','h'=>'0.38']);
if($good['x']!==.1||$good['h']!==.38)throw new RuntimeException('Valid rectangle rejected');
foreach([
 ['x'=>'-0.1','y'=>'0','w'=>'0.2','h'=>'0.3'],
 ['x'=>'0.1','y'=>'0.1','w'=>'0.019','h'=>'0.3'],
 ['x'=>'0.9','y'=>'0.1','w'=>'0.2','h'=>'0.3'],
 ['x'=>'0.1','y'=>'0.8','w'=>'0.3','h'=>'0.4'],
 ['x'=>'INF','y'=>'0.1','w'=>'0.3','h'=>'0.4'],
 ['x'=>'0.1','y'=>'0.1','w'=>'0.3'],
 ['x'=>['0.1'],'y'=>'0.1','w'=>'0.3','h'=>'0.4'],
] as $bad){
 try{$geom::parse($bad);throw new RuntimeException('Invalid rectangle accepted');}
 catch(\InvalidArgumentException){}
}
$read=static fn(string $p):string=>(string)file_get_contents($root.'/'.$p);
$app=$read('app/App.php');$controller=$read('app/Controllers/AdminManualClippingController.php');
$clip=$read('app/Services/ClipService.php');$catalog=$read('app/Services/AdminFeatureCatalog.php');
$view=$read('app/Core/View.php');$ui=$read('app/Views/ManualClippingView.php');
$js=$read('public_html/assets/manual-clipping.js');
foreach(["Auth::requireAdmin();if(\$method==='POST')Http::verifyCsrf();",
 'AdminManualClippingController::dispatch'] as $tok)if(!str_contains($app,$tok))throw new RuntimeException('Admin/CSRF route missing');
foreach(['OWNER','PUBLISHER_ADMIN','EDITOR','Http::privateNoStore()','RateLimiter::allow',
 'TenantData::tenantBin()','ClipService::createEditorial','LIMIT 21','LIMIT 500','LIMIT 24','realpath('] as $tok)
 if(!str_contains($controller,$tok))throw new RuntimeException('Missing security contract '.$tok);
foreach(['clip.manual_created',"'PRIVATE',true",'expiresAt=$editorial?null',
 'if(!$editorial)PlatformClient::instance()->usage'] as $tok)
 if(!str_contains($clip,$tok))throw new RuntimeException('Manual storage contract missing '.$tok);
if(!str_contains($catalog,"'/admin/manual-clipping'"))throw new RuntimeException('Navigation missing');
foreach(['data-manual-stage','data-manual-crop-preview','data-manual-form','Save private clip'] as $tok)
 if(!str_contains($ui,$tok))throw new RuntimeException('Editor UI missing '.$tok);
foreach(['pointerdown','pointermove','pointerup','getContext','data-manual-coord'] as $tok)
 if(!str_contains($js,$tok))throw new RuntimeException('Client selection missing '.$tok);
foreach(["Build::asset('/assets/manual-clipping.css')","Build::asset('/assets/manual-clipping.js')"] as $tok)
 if(!str_contains($view,$tok))throw new RuntimeException('Clipping assets not included');

foreach(['GROUP BY e.edition_guid','COUNT(p.page_guid) ready_pages',
  'JOIN ep_pages p','p.edition_guid=?','checkdate(','has_next','LIMIT 21 OFFSET'] as $tok)
 if(!str_contains($controller,$tok))throw new RuntimeException('Edition-first browse contract missing '.$tok);
foreach(['manual-edition-filters','manual-collection-layout','manual-edition-card',
  'manual-page-picker','selected edition','pageNo','http_build_query'] as $tok)
 if(!str_contains(strtolower($ui),strtolower($tok)))throw new RuntimeException('Edition collection view contract missing '.$tok);
$css=$read('public_html/assets/manual-clipping.css');
foreach(['.manual-edition-list','.manual-edition-pages','.manual-page-pick','@media(max-width:900px)'] as $tok)
 if(!str_contains($css,$tok))throw new RuntimeException('Edition collection responsive CSS missing '.$tok);
$editionGuid='01a14d4c-0ee8-71df-a18e-e714ad1d8bc7';
$pageGuid='01a14d4c-0ee8-71df-a18e-e714ad1d8bc8';
$rendered=\EpaperSaas\Views\ManualClippingView::index(
 [['edition_guid'=>\EpaperSaas\Core\Uuid::toBinary($editionGuid),
   'edition_title'=>'Civic Mirror','edition_date'=>'2026-10-18',
   'publication_name'=>'Civic Mirror','ready_pages'=>1]],
 [['page_guid'=>\EpaperSaas\Core\Uuid::toBinary($pageGuid),'page_number'=>1,'label'=>'Front page']],
 [['publication_guid'=>\EpaperSaas\Core\Uuid::toBinary($editionGuid),'name'=>'Civic Mirror']],
 ['edition_guid'=>\EpaperSaas\Core\Uuid::toBinary($editionGuid),
   'edition_title'=>'Civic Mirror','edition_date'=>'2026-10-18',
   'publication_name'=>'Civic Mirror','ready_pages'=>1],
 ['publication'=>'','date'=>'','page'=>1,'has_next'=>false]
);
if(substr_count($rendered,'class="manual-edition-card active"')!==1 ||
   !str_contains($rendered,'/admin/manual-clipping/'.$pageGuid) ||
   !str_contains($rendered,'Page 1'))
 throw new RuntimeException('Edition page picker rendering contract failed');
echo "Manual clipping and edition-first collection contracts: OK\n";
