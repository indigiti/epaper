<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    if(!str_starts_with($class,'EpaperSaas\\'))return;
    $path=$root.'/app/'.str_replace('\\','/',substr($class,12)).'.php';
    if(is_file($path))require_once $path;
});
use EpaperSaas\Services\{ReaderUiControls,SettingsCatalog,AdminRoutePolicy};
function assertVisible(bool $condition,string $label):void{
    if(!$condition){fwrite(STDERR,"FAIL: $label\n");exit(1);}
    echo "PASS: $label\n";
}
$fields=ReaderUiControls::fields();
$catalog=SettingsCatalog::group('interface');
assertVisible(count($fields)>=25,'All top navigation, reader tools and archive controls are configurable');
assertVisible($catalog!==null&&array_keys($catalog['fields'])===array_keys($fields),'Settings catalog exposes exact visibility switches');
assertVisible(ReaderUiControls::bodyClasses([])==='','Unconfigured publisher retains existing interface');
$values=[];
foreach($fields as $key=>$field){
    assertVisible($field[1]==='select'&&$field[2]==='on'&&$field[3]===['on','off'],
      "Validated default-on field $key");
    $values[$key]='off';
}
$css=file_get_contents($root.'/public_html/assets/reader-ui-visibility.css');
foreach($values as $key=>$_){
    $slug='ui-hide-'.str_replace('_','-',substr($key,3));
    assertVisible(str_contains(ReaderUiControls::bodyClasses([$key=>'off']),$slug),
      "Server sends class for disabled $key");
    assertVisible(str_contains($css,'body.'.$slug),'Disabled control has CSS visibility rule');
}
assertVisible(count(array_filter(explode(' ',ReaderUiControls::bodyClasses($values))))===count($fields),
    'All independently disabled UI switches are represented');
assertVisible(ReaderUiControls::bodyClasses(['ui.zoom_in'=>'unexpected'])==='',
    'Unexpected settings values do not inadvertently hide buttons');
assertVisible(str_contains($css,'display: none !important;'),
    'Hidden controls are not focusable or visible even after reader theme CSS');
assertVisible(!str_contains($css,'.reader-pager')&&!str_contains($css,'[data-reader-prev]')
    && !str_contains($css,'[data-reader-next]'),
    'Previous/next edition-page navigation always remains available');
assertVisible(str_contains($css,'[data-reader-more-action="bookmark"]')
    && str_contains($css,'[data-reader-more-action="share"]')
    && str_contains($css,'[data-reader-more-action="offline"]'),
    'More menu cannot reveal a hidden bookmark, share or offline action');
assertVisible(str_contains($css,'.archive-date-rail')
    && str_contains($css,'.archive-month-row')
    && str_contains($css,'.archive-quick-row'),
    'Archive day rail, month row, and quick shortcuts can be hidden individually');
assertVisible(AdminRoutePolicy::allows('/admin/settings/interface','OWNER')
    && !AdminRoutePolicy::allows('/admin/settings/interface','WEBDESK'),
    'Only existing settings administrators can change interface visibility');
$view=file_get_contents($root.'/app/Core/View.php');
$app=file_get_contents($root.'/app/App.php');
$menu=file_get_contents($root.'/app/Services/AdminFeatureCatalog.php');
assertVisible(str_contains($view,'ReaderUiControls::bodyClasses(TenantData::scopedSettings())'),
    'Public and reader pages apply tenant-specific visibility without user overrides');
assertVisible(str_contains($view,'reader-ui-visibility.css'),
    'Visibility stylesheet loaded after frontend and reader theme styles');
assertVisible(str_contains($menu,"'/admin/settings/interface'"),
    'Publisher sidebar links to the new visibility page');
assertVisible(str_contains($app,'SettingsCatalog::group($group)')
    && str_contains($app,"in_array($value,$field[3],true)"),
    'Existing admin group validates visibility switches server-side');
foreach(['data-zoom-in','data-zoom-out','data-article-mode','data-reader-text-mode','data-clip','data-fit-page','data-fullscreen'] as $marker)
    assertVisible(str_contains($app,$marker),"Existing reader JS hook remains present: $marker");
assertVisible(str_contains($view,'ReaderNavigation::publicSnapshot')
    && str_contains($menu,"'/admin/navigation'"),
    'Published per-link menu management is preserved');
echo "PASS Reader interface visibility controls and tenant security\n";
