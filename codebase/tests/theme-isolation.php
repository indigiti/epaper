<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/Services/ThemeCatalog.php';
use EpaperSaas\Services\ThemeCatalog;

$fails=[];
$check=static function(bool $condition,string $name)use(&$fails):void {
    echo ($condition?'PASS':'FAIL').' — '.$name.PHP_EOL;
    if (!$condition) $fails[]=$name;
};
$frontend=ThemeCatalog::options('frontend');
$reader=ThemeCatalog::options('reader');
$check(count($frontend)>=4 && count($reader)>=5,'Independent frontend and reader catalog choices');
$check(ThemeCatalog::default('frontend')==='Default','Existing frontend appearance preserved by default');
$check(ThemeCatalog::default('reader')==='Newsstand Replica','Existing reader appearance preserved by default');
$check(!ThemeCatalog::accepts('reader','Newsstand Modern'),'No cross-surface theme selection');
$check(!ThemeCatalog::accepts('frontend','Metro Pulse'),'Frontend ignores reader-only themes');
$check(!ThemeCatalog::accepts('frontend','<script>alert(1)</script>'),'Unexpected theme labels denied');
$check(ThemeCatalog::resolve('frontend','Digital Magazine')==='Digital Magazine','Saved frontend skin resolves');
$check(ThemeCatalog::resolve('reader','Editorial Luxe')==='Editorial Luxe','Saved reader skin resolves');
$check(ThemeCatalog::resolve('frontend','Bad Value')==='Default','Invalid saved theme falls back safely');
$check(!ThemeCatalog::previewActive(),'No preview by default');

$view=(string)file_get_contents($root.'/app/Core/View.php');
$app=(string)file_get_contents($root.'/app/App.php');
$settings=(string)file_get_contents($root.'/app/Services/SettingsCatalog.php');
$frontCss=(string)file_get_contents($root.'/public_html/assets/frontend-themes.css');
$readerCss=(string)file_get_contents($root.'/public_html/assets/reader-themes.css');
$check(str_contains($view,"ThemeCatalog::resolve(\$surface,\$savedTheme)"),'Shared View delegates theme resolution');
$check(str_contains($view,'frontend-themes.css')&&str_contains($view,'reader-themes.css'),'Surface-specific CSS linked independently');
$check(str_contains($view,'ThemeCatalog::previewActive()')&&str_contains($view,'noindex,nofollow,noarchive'),'Preview SEO isolation');
$check(str_contains($app,'theme_preview')&&str_contains($app,'theme_surface'),'Admin preview links provided');
$check(str_contains($settings,'appearance.frontend_theme'),'Frontend choice managed through validated tenant settings');
foreach(['newsstand-modern','editorial-portal','classic-newspaper','digital-magazine'] as $slug) {
    $check(str_contains($frontCss,'.public-shell.theme-'.$slug),'Frontend '.$slug.' is styled');
}
foreach(['newsstand-replica','editorial-luxe','metro-pulse','heritage-press','apple-editorial'] as $slug) {
    $check(str_contains($readerCss,'.reader-page.theme-'.$slug),'Reader '.$slug.' is styled');
}
if($fails){fwrite(STDERR,'Theme static regression failed: '.implode(', ',$fails).PHP_EOL);exit(1);}
echo 'PASS independent-theme regression'.PHP_EOL;
