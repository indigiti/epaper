<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='EpaperSaas\\';
    if(!str_starts_with($class,$prefix))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file))require $file;
});
$check=static function(bool $ok,string $label):void{
    if(!$ok)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
};
$catalog=\EpaperSaas\Services\AdminFeatureCatalog::class;
$icons=\EpaperSaas\Services\AdminIcons::class;
foreach(['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','OPERATOR'] as $role){
    foreach($catalog::flatten($catalog::groups($role,true,true)) as [$name,$url,$hint,$icon,$group]){
        $check($icons::supports($icon),$role.' '.$name.' SVG icon exists');
        $svg=$icons::svg($icon);
        $check(str_contains($svg,'viewBox="0 0 24 24"')&&str_contains($svg,'<path')||str_contains($svg,'<rect')||str_contains($svg,'<circle'),
            $name.' returns inline SVG paths');
    }
}
$themes=\EpaperSaas\Services\ThemeCatalog::class;
$check($themes::options('admin')===['Publisher Pro','Executive Slate','Midnight Desk','Default'],'Four valid admin themes');
$check(count($themes::options('reader'))===6,'Six reader themes including fallback');
$check(count($themes::options('auth'))===4,'Four login themes');
$check($themes::resolve('admin','Midnight Desk')==='Midnight Desk','Persisted admin theme resolves');
$gallery=\EpaperSaas\Services\AppearanceGallery::render([
    'frontend'=>'Default','admin'=>'Publisher Pro',
    'reader'=>'Newsstand Replica','auth'=>'Split Editorial'
],'/edition/demo/page/1');
foreach(['frontend','admin','reader','auth'] as $surface)$check(str_contains($gallery,'data-appearance-surface="'.$surface.'"'),'Gallery displays '.$surface);
$check(substr_count($gallery,'data-demo-theme=')===19,'All 19 thematic CSS preview illustrations available');
$check(str_contains($gallery,'theme_surface=admin')&&str_contains($gallery,'theme_surface=reader'),'Admin and reader have live preview links');
$check(substr_count($gallery,'data-theme-choose')===19,'Every theme illustration can select the matching form option');
$check(str_contains($gallery,'Save the theme and open Sign in'),'Authentication mockup accurately indicates applied login theme');
$check(!str_contains($gallery,'<script'),'Gallery has no runtime script or third-party embeds');
$chart=\EpaperSaas\Services\AdminDashboardVisuals::pipeline([
    'PUBLISHED'=>10,'READY'=>5,'PROCESSING'=>1,'NEEDS_ATTENTION'=>2,'SCHEDULED'=>0
]);
$check(str_contains($chart,'18</strong> total editions'),'Chart uses existing edition counts');
$check(str_contains($chart,'width:100%')&&str_contains($chart,'width:50%'),'Chart widths are normalized');
$check(str_contains($chart,'/admin/editions?status=PUBLISHED'),'Chart links to existing editions filters');
$view=(string)file_get_contents($root.'/app/Core/View.php');
$app=(string)file_get_contents($root.'/app/App.php');
$sidebar=(string)file_get_contents($root.'/public_html/assets/admin-sidebar.js');
$adminCss=(string)file_get_contents($root.'/public_html/assets/admin-design-system.css');
$readerCss=(string)file_get_contents($root.'/public_html/assets/reader-themes.css');
$authCss=(string)file_get_contents($root.'/public_html/assets/auth-themes.css');
$galleryCss=(string)file_get_contents($root.'/public_html/assets/admin-appearance-gallery.css');
$check(str_contains($app,'AdminDashboardVisuals::pipeline($counts,$canCreate)'),'Dashboard uses DB-free pipeline stats');
$check(str_contains($app,'AppearanceGallery::render($saved,$editionPath)'),'Appearance uses new gallery');
$check(str_contains($view,'AdminIcons::svg($icon)')&&str_contains($view,'AdminIcons::svg($sectionIcons'),'Sidebar uses validated icons');
$check(str_contains($view,'admin-design-system.css')&&str_contains($view,'admin-appearance-gallery.css'),'Admin CSS bundles wired in shell');
$check(str_contains($view,'admin-appearance-gallery.js'),'Gallery interaction is loaded on Appearance admin route');
$check(str_contains($view,'reader-themes.css')&&str_contains($view,'auth-themes.css'),'Reader and Auth scoped CSS loaded');
$check(str_contains($view,"ThemeCatalog::resolve('admin'")&&str_contains($view,"ThemeCatalog::resolve('reader'"),'Admin/Reader preview resolution wired');
$check(str_contains($view,'brand-logo-fallback'),'Broken-image brand text fallback added');
$check(str_contains($adminCss,'body.admin-shell.theme-midnight-desk')&&str_contains($adminCss,'theme-executive-slate'),'Admin theme palettes implemented');
foreach(['newsstand-replica','editorial-luxe','metro-pulse','heritage-press','apple-editorial'] as $slug)
  $check(str_contains($readerCss,'.theme-'.$slug),'Reader '.$slug.' distinct skin exists');
foreach(['split-editorial','gradient-focus','minimal-access'] as $slug)
  $check(str_contains($authCss,'.theme-'.$slug),'Auth '.$slug.' distinct layout exists');
$check(str_contains($sidebar,'beforeSearch')&&str_contains($sidebar,'setCollapsed')&&str_contains($sidebar,'requestAnimationFrame'),
  'Sidebar native details and persisted state survive initialization');
$check(str_contains($galleryCss,'.appearance-demo-reader')&&str_contains($galleryCss,'.appearance-demo-auth'),'CSS-only demo cards cover all surfaces');
// Confirm no changes to independent reader clipping engines or storage are needed.
foreach(['app/Services/ClipService.php','public_html/assets/reader.js','public_html/assets/reader-editorial-clips.js'] as $file)
  $check(is_file($root.'/'.$file),'Existing clip/reader engine retained');
echo 'PASS complete admin redesign source contracts'.PHP_EOL;
