<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='EpaperSaas\\';
    if(!str_starts_with($class,$prefix))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file))require_once $file;
});
use EpaperSaas\Services\{AdminRoutePolicy,AdminFeatureCatalog};
function checkWebDesk(bool $value,string $reason):void{
    if(!$value){fwrite(STDERR,"FAIL: $reason\n");exit(1);}
    echo "PASS: $reason\n";
}
$uuid='01a14d4c-0ee8-71df-a18e-e714ad1d8bc8';
$mark='01a14d4c-0ee8-71df-a18e-e714ad1d8bc9';
$allowed=[
    ['/admin','GET'],
    ['/admin/editions','GET'],['/admin/editions','POST'],
    ["/admin/editions/$uuid/publish",'POST'],
    ['/admin/manual-clipping','GET'],
    ["/admin/manual-clipping/$uuid",'GET'],["/admin/manual-clipping/$uuid",'POST'],
    ["/admin/manual-clipping/$uuid/preview",'GET'],
    ["/admin/manual-clipping/$uuid/marks/$mark/update",'POST'],
    ['/admin/account','GET'],
];
foreach($allowed as [$path,$verb])
    checkWebDesk(AdminRoutePolicy::allows($path,'WEBDESK',$verb),"WebDesk allowed $verb $path");
$denied=[
    ['/admin/editions','DELETE'],
    ["/admin/editions/$uuid/publish",'GET'],
    ["/admin/editions/$uuid/archive",'POST'],
    ["/admin/editions/$uuid/trash",'POST'],
    ["/admin/editions/$uuid/delete",'POST'],
    ["/admin/editions/$uuid/owner-remove",'POST'],
    ["/admin/editions/$uuid/restore",'POST'],
    ["/admin/editions/$uuid/update",'POST'],
    ["/admin/editions/$uuid/schedule",'POST'],
    ["/admin/editions/$uuid/unschedule",'POST'],
    ["/admin/editions/$uuid/process-now",'POST'],
    ["/admin/editions/$uuid/reprocess",'POST'],
    ["/admin/editions/$uuid/pages",'GET'],
    ["/admin/editions/$uuid/pages",'POST'],
    ["/admin/editions/$uuid/recover",'POST'],
    ["/admin/editions/$uuid/cancel",'POST'],
    ['/admin/processing','GET'],['/admin/settings','GET'],
    ['/admin/access','GET'],['/admin/publications','GET'],
    ['/admin/system/infrastructure','GET'],['/admin/ai-clipping','GET'],
    ["/admin/manual-clipping/$uuid/marks/$mark/remove",'POST'],
    ["/admin/manual-clipping/$uuid/marks/$mark/hide",'POST'],
    ["/admin/manual-clipping/$uuid/marks/$mark/activate",'POST'],
    ["/admin/manual-clipping/$uuid/marks/$mark/publish-official",'POST'],
    ["/admin/manual-clipping/$uuid/marks/$mark/withdraw-official",'POST'],
    ["/admin/manual-clipping/$uuid/import/0123456789abcdef01",'POST'],
    ['/admin/account','POST'],
    ['/admin/audit','GET'],
];
foreach($denied as [$path,$verb])
    checkWebDesk(!AdminRoutePolicy::allows($path,'WEBDESK',$verb),"WebDesk denied $verb $path");
foreach(['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR'] as $role)
    checkWebDesk(AdminRoutePolicy::allows('/admin/editions',$role,'GET'),
        "Existing editorial access retained: $role");
$groups=AdminFeatureCatalog::groups('WEBDESK',true,true);
$links=array_column(AdminFeatureCatalog::flatten($groups),1);
checkWebDesk($links===['/admin','/admin/editions','/admin/manual-clipping','/admin/account'],
    'WebDesk navigation exposes only publishing tools and account');
$auth=file_get_contents($root.'/app/Core/Auth.php');
$app=file_get_contents($root.'/app/App.php');
$controller=file_get_contents($root.'/app/Controllers/AdminManualClippingController.php');
$marks=file_get_contents($root.'/app/Services/ManualClipMarking.php');
$view=file_get_contents($root.'/app/Views/ManualClippingView.php');
$desk=file_get_contents($root.'/app/Views/WebDeskEditionView.php');
checkWebDesk(str_contains($auth,"!=='WEBDESK'"),'WebDesk accepted by authenticated tenant membership');
checkWebDesk(str_contains($app,'AdminRoutePolicy::enforce($path,$method)'),
    'Every admin method checked before dispatcher');
checkWebDesk(str_contains($app,"\\EpaperSaas\\Views\\WebDeskEditionView::index();"),
    'Restricted editions view used for WebDesk');
checkWebDesk(str_contains($app,"\$publishMode='MANUAL';"),
    'WebDesk uploads cannot enable automatic publishing');
checkWebDesk(str_contains($app,"?['READY']:['READY','SCHEDULED']"),
    'WebDesk publication requires READY edition');
checkWebDesk(str_contains($controller,"'EDITOR','WEBDESK'"),
    'Manual clip controller accepts restricted WebDesk');
checkWebDesk(str_contains($controller,"Only article outlines may be saved by WebDesk"),
    'WebDesk private clipping blocked server-side');
checkWebDesk(str_contains($marks,"'EDITOR','WEBDESK'"),'WebDesk must own outlines it edits');
checkWebDesk(str_contains($marks,"Only editors can remove an outline") &&
    str_contains($marks,"Only editors can change outline visibility"),
    'Manual mark mutations are independently protected');
checkWebDesk(str_contains($view,"strtoupper($role)!=='WEBDESK'") &&
    str_contains($view,"strtoupper($role)==='WEBDESK'"),
    'WebDesk clipping UI hides controls outside permission');
checkWebDesk(str_contains($desk,'action="/admin/editions/')&&
    !str_contains($desk,'/archive')&&!str_contains($desk,'/trash')&&
    !str_contains($desk,'/restore')&&!str_contains($desk,'/schedule'),
    'Restricted editions screen never renders forbidden forms');
echo "PASS — WebDesk publisher permissions and tenant protections\n";
