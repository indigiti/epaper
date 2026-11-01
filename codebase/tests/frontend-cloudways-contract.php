<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/Services/ThemeCatalog.php';
use EpaperSaas\Services\ThemeCatalog;

$fails=[];
$check=static function(bool $condition,string $label) use (&$fails):void {
    echo ($condition?'PASS':'FAIL').' '.$label.PHP_EOL;
    if(!$condition)$fails[]=$label;
};
$themes=['Default','Newsstand Modern','Editorial Portal','Classic Newspaper','Digital Magazine'];
$check(ThemeCatalog::options('frontend')===$themes,'Five approved frontend choices, Default first');
$check(ThemeCatalog::resolve('frontend','Default')==='Default','Existing default left intact');
$check(ThemeCatalog::resolve('frontend','Editorial Portal')==='Editorial Portal','Tenant selection respected');
$check(ThemeCatalog::resolve('frontend','<img src=x onerror=alert(1)>')==='Default','Invalid theme rejected');
$check(!ThemeCatalog::previewActive(),'No preview enabled without valid manager request');
$catalog=(string)file_get_contents($root.'/app/Services/SettingsCatalog.php');
$view=(string)file_get_contents($root.'/app/Core/View.php');
$app=(string)file_get_contents($root.'/app/App.php');
$policy=(string)file_get_contents($root.'/app/Services/AdminRoutePolicy.php');
$css=(string)file_get_contents($root.'/public_html/assets/frontend-layouts.css');
$check(str_contains($catalog,'appearance.frontend_theme'),'Tenant Appearance settings include frontend selector');
$check(str_contains($view,"ThemeCatalog::resolve('frontend',"),'Public shell uses tenant frontend theme');
$check(str_contains($view,"ThemeCatalog::previewActive()")&&str_contains($view,'noindex,nofollow,noarchive'),'Private preview has noindex');
$check(str_contains($view,'frontend-themes.css')&&str_contains($view,'frontend-layouts.css'),'Public stylesheets added');
$check(str_contains($view,'reader-editorial-clips.css')&&str_contains($view,'clip-ui-compact.css'),'Existing Reader and clipping styles remain');
$check(str_contains($app,'FrontendHomeLayouts::render($selectedHomeTheme'),'Homepage renders selected layout');
$check(str_contains($app,'$homeContent=$resume.$hero.$recent.$pubHtml.$archiveCta.$hi'),'Original homepage remains default');
$check(str_contains($app,'AppearanceGallery::render($saved,$editionPath)'),'Publisher admin preview studio present');
$check(str_contains($app,'AdminRoutePolicy::enforce($path)'),'Cloudways admin policy still enforced');
$check(str_contains($policy,"'/admin/settings'")&&str_contains($policy,'MANAGER'),'Only managers may configure appearance');
foreach(['newsstand','editorial','newspaper','magazine'] as $surface)$check(str_contains($css,'fth-'.$surface),'Has scoped '.$surface.' layout rules');
if($fails){fwrite(STDERR,"Frontend port contract failed: ".implode(', ',$fails).PHP_EOL);exit(1);}
echo "PASS Cloudways frontend-theme contract".PHP_EOL;
