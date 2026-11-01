<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='EpaperSaas\\';if(!str_starts_with($class,$prefix))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file))require_once $file;
});
use EpaperSaas\Services\{ReaderUiControls,SettingsCatalog};
function assertEdge(bool $ok,string $name):void{
    if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}
    echo "PASS: $name\n";
}
$fields=SettingsCatalog::group('interface')['fields']??[];
foreach([
 'ui.reader_edge_navigation'=>['on',['on','off']],
 'ui.reader_edge_opacity'=>['subtle',['subtle','balanced','strong']],
 'ui.reader_edge_style'=>['dots',['dots','chevron']],
] as $key=>[$default,$options]){
    assertEdge(isset($fields[$key])&&$fields[$key][1]==='select'
        &&$fields[$key][2]===$default&&$fields[$key][3]===$options,
        "Edge setting $key validated");
}
assertEdge(str_contains(ReaderUiControls::bodyClasses(['ui.reader_edge_navigation'=>'off']),
 'ui-hide-reader-edge-navigation'),'Tenant hide class is generated');
assertEdge(!str_contains(ReaderUiControls::bodyClasses([]),'ui-hide-reader-edge-navigation'),
 'Default is visible');
$app=file_get_contents($root.'/app/App.php');
$view=file_get_contents($root.'/app/Core/View.php');
$js=file_get_contents($root.'/public_html/assets/reader-edge-navigation.js');
$css=file_get_contents($root.'/public_html/assets/reader-edge-navigation.css');
$hide=file_get_contents($root.'/public_html/assets/reader-ui-visibility.css');
assertEdge(str_contains($app,"TenantData::setting('ui.reader_edge_opacity'")
 &&str_contains($app,"TenantData::setting('ui.reader_edge_style'")
 &&str_contains($app,'data-edge-opacity=')&&str_contains($app,'data-edge-style='),
 'Valid tenant preferences passed into reader');
assertEdge(str_contains($view,'/assets/reader-edge-navigation.css')
 &&str_contains($view,'/assets/reader-edge-navigation.js'),'Revisioned assets loaded');
assertEdge(str_contains($hide,'body.ui-hide-reader-edge-navigation .reader-edge-nav'),
 'Admin visibility switch hides edge navigation');
assertEdge(str_contains($js,'link.click()')&&str_contains($js,'[data-reader-prev]')
 &&str_contains($js,'[data-reader-next]')&&str_contains($js,"mode==='continuous'"),
 'Existing pager controls all reader modes');
assertEdge(str_contains($js,"attributeFilter:['class','aria-disabled']")
 &&str_contains($js,'data-page-number')&&str_contains($js,'label?.textContent'),
 'Buttons react to page, spread and continuous progress');
assertEdge(str_contains($js,'e.stopPropagation()')
 &&str_contains($css,'touch-action:manipulation')
 &&str_contains($css,'.reader-shell.clip-active .reader-edge-nav')
 &&str_contains($css,'.reader-stage.article-pick .reader-edge-nav'),
 'No competing pan, pinch, swipe or clipping handlers');
assertEdge(str_contains($css,'.reader-edge-button:disabled')
 &&str_contains($css,'.reader-edge-button:focus-visible')
 &&str_contains($css,'@media(pointer:coarse)'),
 'Disabled bounds, keyboard focus and touch hit targets');
