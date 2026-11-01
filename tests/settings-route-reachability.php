<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='EpaperSaas\\';
    if(!str_starts_with($class,$prefix))return;
    $path=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require_once $path;
});
use EpaperSaas\Services\{SettingsCatalog,AdminRoutePolicy,AdminFeatureCatalog};
function checkSettingsRoute(bool $ok,string $message):void{
    if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}
$groups=SettingsCatalog::groups();
checkSettingsRoute(array_key_exists('interface',$groups),
    'Interface Visibility group exists in registered tenant settings');
foreach($groups as $group=>$config){
    $url='/admin/settings/'.$group;
    checkSettingsRoute(SettingsCatalog::routeGroup($url)===$group,
        'Admin settings router recognizes published tab '.$url);
    checkSettingsRoute(AdminRoutePolicy::allows($url,'OWNER','GET')
        && AdminRoutePolicy::allows($url,'OWNER','POST'),
        'Owner can read and save '.$url);
    checkSettingsRoute(!AdminRoutePolicy::allows($url,'WEBDESK','GET')
        && !AdminRoutePolicy::allows($url,'WEBDESK','POST'),
        'Restricted WebDesk cannot modify '.$url);
}
foreach([
 '/admin/settings',
 '/admin/settings/',
 '/admin/settings/not-registered',
 '/admin/settings/interface/anything',
 '/admin/settings/interface/',
 '/admin/settings/interface?foo=bar',
 '/admin/settings/INTERFACE',
 '/admin/settings/%2e%2e',
 '/admin/settings/../interface',
 '/admin/settings/interface;delete',
 '/admin/settings/interface/../../secret',
 '/ADMIN/settings/interface',
 '/admin/settings/interface\n',
] as $path){
    checkSettingsRoute(SettingsCatalog::routeGroup($path)===null,
        'Unknown or ambiguous settings route rejected');
}
$links=array_column(AdminFeatureCatalog::flatten(
    AdminFeatureCatalog::groups('OWNER',true,false)),1);
checkSettingsRoute(in_array('/admin/settings/interface',$links,true),
    'Interface tab is discoverable in admin sidebar');
$settings=(string)file_get_contents($root.'/app/Services/SettingsCatalog.php');
$app=(string)file_get_contents($root.'/app/App.php');
checkSettingsRoute(str_contains($app,'SettingsCatalog::routeGroup($path)')
        && str_contains($app,'$this->adminSettingsGroup($settingsGroup,$method)'),
    'Real HTTP admin dispatcher uses catalogue-based route matching');
checkSettingsRoute(!str_contains($app,
    'appearance|effects|brand|seo|trust|access|reader|processing|clipping)$'),
    'Old hardcoded settings allowlist has been removed');
checkSettingsRoute(str_contains($app,'SettingsCatalog::group($group)')
        && str_contains($app,'TenantData::setSetting($key,$value,'),
    'GET and POST use existing settings form and per-tenant persistence');
checkSettingsRoute(str_contains($app,'AdminRoutePolicy::enforce($path,$method)'),
    'Admin authorization runs before settings route dispatch');
checkSettingsRoute(str_contains($settings,"'interface' => ["),
    'Interface tab defined by SettingsCatalog');
echo "PASS settings route reachability regression\n";
