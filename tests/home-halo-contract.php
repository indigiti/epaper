<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='EpaperSaas\\';
    if(!str_starts_with($class,$prefix))return;
    $path=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require_once $path;
});
$check=static function(bool $pass,string $message):void{
    echo ($pass?'PASS ':'FAIL ').$message.PHP_EOL;
    if(!$pass)throw new RuntimeException($message);
};
$halo=\EpaperSaas\Services\PublicHalo::class;
$cases=[
    ['Default','<section class="newsstand-hero"><h1>Today</h1></section>',true],
    ['Newsstand Modern','<div><section class="fth-hero fth-newsstand-hero"><h1>Today</h1></section></div>',true],
    ['Digital Magazine','<div><section class="fth-hero fth-magazine-hero"><h1>Today</h1></section></div>',true],
    ['Editorial Portal','<section class="fth-editorial-lead"><h1>Today</h1></section>',false],
    ['Classic Newspaper','<section class="fth-paper-lead"><h1>Today</h1></section>',false],
];
foreach($cases as [$theme,$input,$supported]){
    $out=$halo::decorate($input,$theme,true);
    $check(str_contains($out,'data-epaper-halo')===$supported,$theme.' does not affect unsupported themes');
    $check(str_contains($out,'<h1>Today</h1>'),$theme.' keeps original content');
    if($supported){
        $check(str_contains($out,'class="')&&str_contains($out,'has-vanta-halo"'),$theme.' has a valid CSS class');
        $check(substr_count($out,'data-epaper-halo')===1,$theme.' has exactly one effect container');
        $check(str_contains($out,'data-three-src="/assets/vendor/three.r134.min.js?v='),$theme.' loads same-origin pinned Three');
        $check(str_contains($out,'data-halo-src="/assets/vendor/vanta.halo.0.5.24.min.js?v='),$theme.' loads same-origin pinned HALO');
    }
    $check($halo::decorate($input,$theme,false)===$input,$theme.' remains byte-identical when setting Off');
}
$settings=\EpaperSaas\Services\SettingsCatalog::group('appearance')['fields']??[];
$check(isset($settings['appearance.home_background_effect']),'Publisher Appearance includes HALO option');
$check($settings['appearance.home_background_effect'][2]==='Off','HALO is disabled by default');
$check($settings['appearance.home_background_effect'][3]===['Off','Halo'],'HALO setting is constrained to exact allowed values');

$src=(string)file_get_contents($root.'/app/Services/PublicHalo.php');
$app=(string)file_get_contents($root.'/app/App.php');
$view=(string)file_get_contents($root.'/app/Core/View.php');
$http=(string)file_get_contents($root.'/app/Core/Http.php');
$js=(string)file_get_contents($root.'/public_html/assets/home-halo.js');
$css=(string)file_get_contents($root.'/public_html/assets/home-halo.css');
$check(str_contains($src,"['OWNER','ADMIN','PUBLISHER_ADMIN']")&&str_contains($src,'Http::privateNoStore()'),'Preview requires manager and noncacheable response');
$check(str_contains($src,'Auth::currentMembership()'),'Reader cannot activate manager preview');
$check(str_contains($app,"PublicHalo::decorate(\$homeContent,\$selectedHomeTheme)"),'Home page calls tenant-scoped decoration only after theme selection');
$check(str_contains($view,"(bool)(\$opts['home_halo']??false)"),'Layout gates dedicated HALO assets by homepage flag');
$check(str_contains($view,'assets/home-halo.css')&&str_contains($view,'assets/home-halo.js'),'HALO assets conditionally included');
$check(str_contains($http,"script-src 'self'"),'Strict same-origin script policy remains unchanged');
$check(str_contains($js,"prefers-reduced-motion: reduce")&&str_contains($js,'deviceMemory')&&str_contains($js,'saveData'),'Motion, low-end and metered connection gating');
$check(str_contains($js,'IntersectionObserver')&&str_contains($js,'visibilitychange')&&str_contains($js,'effect.destroy()'),'Viewport/tab lifecycle cleanup included');
$check(str_contains($css,'.has-vanta-halo.newsstand-hero')&&str_contains($css,'.has-vanta-halo.fth-hero'),'Default and custom themes receive scoped fallbacks');

foreach(['three.r134.min.js','vanta.halo.0.5.24.min.js','THREE-LICENSE.txt','VANTA-LICENSE.md'] as $file){
    $check(is_file($root.'/public_html/assets/vendor/'.$file),'Local pinned vendor asset '.$file);
}
$check(filesize($root.'/public_html/assets/vendor/three.r134.min.js')<700000,'Three dependency size is capped');
$check(filesize($root.'/public_html/assets/vendor/vanta.halo.0.5.24.min.js')<30000,'HALO effect bundle size is capped');
$check(strlen($js)<8000,'Effect loader stays lightweight');
echo 'PASS Vanta HALO source and scope contract'.PHP_EOL;
