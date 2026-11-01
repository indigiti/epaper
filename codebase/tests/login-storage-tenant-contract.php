<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='EpaperSaas\\';
    if(!str_starts_with($class,$prefix))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file))require_once $file;
});
use EpaperSaas\Services\{ReaderUiControls,SettingsCatalog,AdminRoutePolicy,TenantResourceReport};
function checkLoginStorage(bool $ok,string $case):void {
    if(!$ok){fwrite(STDERR,"FAIL: $case\n");exit(1);}
    echo "PASS: $case\n";
}
$fields=SettingsCatalog::group('interface')['fields']??[];
foreach(['ui.login_app_approval','ui.login_mobile_otp'] as $key){
    checkLoginStorage(isset($fields[$key])&&$fields[$key][1]==='select'
        &&$fields[$key][2]==='on'&&$fields[$key][3]===['on','off'],
        'Tenant UI has secure default-on control '.$key);
}
$auth=file_get_contents($root.'/app/Core/Auth.php');
$view=file_get_contents($root.'/app/Core/View.php');
$app=file_get_contents($root.'/app/App.php');
$css=file_get_contents($root.'/public_html/assets/reader-ui-visibility.css');
$resource=file_get_contents($root.'/app/Services/TenantResourceReport.php');
$storage=file_get_contents($root.'/app/Controllers/TenantStorageController.php');
$catalog=file_get_contents($root.'/app/Services/AdminFeatureCatalog.php');
checkLoginStorage(str_contains($auth,"\$loginSettings['ui.login_app_approval']")
    &&str_contains($auth,"\$loginSettings['ui.login_mobile_otp']"),
    'Sign-in buttons are conditionally rendered using tenant settings');
checkLoginStorage(str_contains($auth, 'ReaderSession,TenantData};')
    &&class_exists(\EpaperSaas\Services\TenantData::class),
    'Publisher login resolves the tenant settings service without a missing-class error');
checkLoginStorage(str_contains($auth,'Sign in to Publisher Desk')&&str_contains($auth,'Publisher workspace'),
    'Clear Publisher Desk button and section labels');
checkLoginStorage(str_contains($view,"self::icon('user').'</a>';"),
    'Public sign-in button uses familiar user icon, not logout arrow');
checkLoginStorage(str_contains($css,'body.ui-hide-login-app-approval .auth-app-approval')
    &&str_contains($css,'body.ui-hide-login-mobile-otp .auth-mobile-otp'),
    'Sign-in presentation rules hide designated buttons only');
checkLoginStorage(str_contains($view,'$visibilityClasses=$hasTenant&&!$workspace'),
    'Tenant visibility preferences reach authentication page');
checkLoginStorage(str_contains($auth,'PlatformClient::instance()->directAuth'),
    'Publisher password auth stays reachable regardless of app/OTP visibility');
checkLoginStorage(str_contains($app,'$visibleSection=')&&str_contains($app,"\$group==='interface'"),
    'Interface Visibility settings arranged in distinct sections');
checkLoginStorage(str_contains($catalog,"'Tenant Usage & Storage','/admin/storage'"),
    'Storage report moved under Operations');
checkLoginStorage(str_contains($app,'TenantStorageController::index()')
    &&str_contains($app,"TenantStorageController::refresh()"),
    'Admin storage and safe optimize endpoints dispatched');
checkLoginStorage(AdminRoutePolicy::allows('/admin/storage','OWNER','GET')
    &&!AdminRoutePolicy::allows('/admin/storage','WEBDESK','GET')
    &&!AdminRoutePolicy::allows('/admin/storage/refresh-cache','WEBDESK','POST'),
    'Storage reports and maintenance denied to WebDesk');
checkLoginStorage(str_contains($resource,'tenant_guid=?')
    &&str_contains($resource,'Uuid::toBinary($tenant)')
    &&str_contains($resource,'MultiLayerCache::remember'),
    'Database metrics and caches stay bound to tenant ID');
checkLoginStorage(str_contains($resource,'db_tenant_physical_bytes'),
    'Database report does not invent shared InnoDB tenant allocations');
checkLoginStorage(str_contains($resource,'$maxFiles=2500')&&str_contains($resource,'$maxSeconds=0.20')
    &&str_contains($resource,'$file->isLink()'),
    'Filesystem scan is time and count bounded and skips symlinks');
checkLoginStorage(str_contains($storage,"if(!in_array(\$role,['OWNER','ADMIN','PUBLISHER_ADMIN'],true))")
    &&str_contains($storage,"RuntimeCache::broadcast(\$tenant,")
    &&str_contains($storage,"MultiLayerCache::forget('tenant-utilization:v1')"),
    'Cache refresh is authorized, tenant-scoped and forgets report snapshot');
checkLoginStorage(!str_contains($storage,'DELETE FROM')&&!str_contains($storage,'unlink(')
    &&!str_contains($storage,'rmdir('),
    'Optimization cannot delete reader records or edition media');
$tmp=sys_get_temp_dir().'/epdi-tenant-usage-test-'.bin2hex(random_bytes(5));
mkdir($tmp,0700,true);
file_put_contents($tmp.'/a.bin','ABCD');
file_put_contents($tmp.'/b.bin','xyz');
$snap=TenantResourceReport::scanDirectory($tmp,10,10.0);
checkLoginStorage($snap['complete']&&$snap['files']===2&&$snap['bytes']===7,
    'Tenant disk scanner measures a small directory accurately');
$cut=TenantResourceReport::scanDirectory($tmp,1,10.0);
checkLoginStorage(!$cut['complete']&&$cut['files']===1,
    'Tenant disk scan reports partial results instead of claiming exact bytes');
unlink($tmp.'/a.bin');unlink($tmp.'/b.bin');rmdir($tmp);
echo "PASS per-tenant sign-in and storage usage contracts\n";
