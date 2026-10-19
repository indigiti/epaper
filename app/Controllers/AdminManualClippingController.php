<?php
declare(strict_types=1);
namespace EpaperSaas\Controllers;

use EpaperSaas\Core\{Auth,Http,RateLimiter,RequestContext,Store,Uuid,View};
use EpaperSaas\Services\{Audit,ClipService,ManualClipGeometry,TenantData};
use EpaperSaas\Views\ManualClippingView;

/** Authenticated publisher-only page selection, preview, and clip creation. */
final class AdminManualClippingController
{
    private static function allow(): void
    {
        $role=strtoupper((string)(Auth::currentMembership()['role_key']??''));
        if(!in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR'],true)){
            http_response_code(403);exit('Editorial clipping permission required');
        }
        Http::privateNoStore();
    }

    public static function dispatch(string $path,string $method): never
    {
        self::allow(); // App::runAdmin also authenticates every route and verifies CSRF on POST.
        if($path==='/admin/manual-clipping'&&$method==='GET')self::index();
        if(preg_match('#^/admin/manual-clipping/([0-9a-f-]{36})/preview$#i',$path,$m)&&$method==='GET')
            self::preview(strtolower($m[1]));
        if(preg_match('#^/admin/manual-clipping/([0-9a-f-]{36})$#i',$path,$m)){
            if($method==='GET')self::detail(strtolower($m[1]));
            if($method==='POST')self::save(strtolower($m[1]));
        }
        http_response_code(404);exit('Not found');
    }

    private static function page(string $guid): ?array
    {
        if(!Uuid::isValid($guid))return null;
        return Store::one(
            'SELECT p.page_guid,p.edition_guid,p.page_number,p.label,e.title edition_title,
                    e.edition_date,pub.name publication_name,
                    preview.storage_path preview_path,preview.mime_type preview_mime,
                    hires.storage_path highres_path
             FROM ep_pages p
             JOIN ep_editions e ON e.tenant_guid=p.tenant_guid AND e.edition_guid=p.edition_guid
             JOIN ep_publications pub ON pub.tenant_guid=e.tenant_guid AND pub.publication_guid=e.publication_guid
             LEFT JOIN ep_page_assets preview ON preview.tenant_guid=p.tenant_guid
                   AND preview.page_guid=p.page_guid AND preview.asset_type="PREVIEW"
             LEFT JOIN ep_page_assets hires ON hires.tenant_guid=p.tenant_guid
                   AND hires.page_guid=p.page_guid AND hires.asset_type="HIGHRES"
             WHERE p.tenant_guid=? AND p.page_guid=? AND e.status NOT IN ("TRASHED") LIMIT 1',
            [TenantData::tenantBin(),Uuid::toBinary($guid)]
        );
    }

    private static function index(): never
    {
        $rows=Store::all(
            'SELECT p.page_guid,p.page_number,e.title edition_title,e.edition_date,
                    pub.name publication_name FROM ep_pages p
             JOIN ep_editions e ON e.tenant_guid=p.tenant_guid AND e.edition_guid=p.edition_guid
             JOIN ep_publications pub ON pub.tenant_guid=e.tenant_guid AND pub.publication_guid=e.publication_guid
             JOIN ep_page_assets preview ON preview.tenant_guid=p.tenant_guid
                   AND preview.page_guid=p.page_guid AND preview.asset_type="PREVIEW"
             JOIN ep_page_assets hires ON hires.tenant_guid=p.tenant_guid
                   AND hires.page_guid=p.page_guid AND hires.asset_type="HIGHRES"
             WHERE p.tenant_guid=? AND e.status NOT IN ("TRASHED")
             ORDER BY e.edition_date DESC,p.page_number ASC LIMIT 80',
            [TenantData::tenantBin()]
        );
        View::layout('Manual clipping',ManualClippingView::index($rows),[
            'brand'=>(string)RequestContext::get('tenant_name','ePaper SaaS'),'admin'=>true]);
    }

    private static function detail(string $guid,?string $error=null): never
    {
        $page=self::page($guid);
        if(!$page){http_response_code(404);exit('Page not found');}
        $actor=(string)(Auth::user()['user_guid']??'');
        $clips=Uuid::isValid($actor)?Store::all(
            'SELECT clip_slug,title,created_at FROM ep_clips
             WHERE tenant_guid=? AND page_guid=? AND platform_user_guid=? AND expires_at IS NULL
             ORDER BY created_at DESC LIMIT 24',
            [TenantData::tenantBin(),Uuid::toBinary($guid),Uuid::toBinary($actor)]
        ):[];
        $created=(string)($_GET['created']??'');
        $notice=preg_match('/^[a-f0-9]{18}$/D',$created)?'/c/'.$created:'';
        View::layout('Manual clipping · Page '.(int)$page['page_number'],
            ManualClippingView::detail($page,$guid,$clips,$notice,$error),
            ['brand'=>(string)RequestContext::get('tenant_name','ePaper SaaS'),'admin'=>true]);
    }

    private static function preview(string $guid): never
    {
        $row=self::page($guid);
        if(!$row||empty($row['preview_path'])||
           !in_array((string)$row['preview_mime'],['image/webp','image/jpeg','image/png'],true)){
            http_response_code(404);exit;
        }
        $root=dirname(__DIR__,2);$tenant=RequestContext::tenantGuid();
        $safe=realpath($root.'/storage/media/'.$tenant.'/'.Uuid::fromBinary($row['edition_guid']));
        $file=realpath($root.'/storage/'.ltrim((string)$row['preview_path'],'/'));
        if(!$safe||!$file||!str_starts_with($file,$safe.DIRECTORY_SEPARATOR)||
           !is_file($file)||filesize($file)>25*1024*1024){
            http_response_code(404);exit;
        }
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: '.$row['preview_mime']);
        header('Content-Length: '.filesize($file));
        readfile($file);exit;
    }

    private static function save(string $guid): never
    {
        $row=self::page($guid);
        if(!$row){http_response_code(404);exit('Page not found');}
        if(empty($row['preview_path'])||empty($row['highres_path']))
            self::detail($guid,'Page processing must finish before clipping.');
        $actor=(string)(Auth::user()['user_guid']??'');
        if(!Uuid::isValid($actor)){http_response_code(403);exit('Editor identity required');}
        if(!RateLimiter::allow('manual-clip:'.RequestContext::tenantGuid().':'.$actor,30,60)){
            http_response_code(429);self::detail($guid,'Too many clipping requests; try again shortly.');
        }
        try{
            $box=ManualClipGeometry::parse($_POST);
            $title=trim((string)($_POST['title']??''));
            if(mb_strlen($title)>180)throw new \InvalidArgumentException('Title is too long');
            $clip=ClipService::createEditorial(
                $guid,$box['x'],$box['y'],$box['w'],$box['h'],$title);
            Http::redirect('/admin/manual-clipping/'.$guid.'?created='.rawurlencode($clip['slug']));
        }catch(\InvalidArgumentException $e){
            http_response_code(422);self::detail($guid,$e->getMessage());
        }catch(\Throwable $e){
            Audit::log('clip.manual_failed','page',$guid,'FAILED',['message'=>$e->getMessage()]);
            http_response_code(503);self::detail($guid,'Clipping could not be saved. Check page media and image processing.');
        }
    }
}
