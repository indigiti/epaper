<?php
declare(strict_types=1);
namespace EpaperSaas\Controllers;

use EpaperSaas\Core\{Auth,Http,RateLimiter,RequestContext,Store,Uuid,View};
use EpaperSaas\Services\{Audit,ClipService,ManualClipGeometry,ManualClipMarking,OfficialClipService,TenantData};
use EpaperSaas\Views\ManualClippingView;

/** Authenticated publisher-only page selection, preview, and clip creation. */
final class AdminManualClippingController
{
    private static function allow(): void
    {
        $role=strtoupper((string)(Auth::currentMembership()['role_key']??''));
        if(!in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','WEBDESK'],true)){
            http_response_code(403);exit('Editorial clipping permission required');
        }
        Http::privateNoStore();
    }

    public static function dispatch(string $path,string $method): never
    {
        self::allow(); // App::runAdmin also authenticates every route and verifies CSRF on POST.
        if($path==='/admin/manual-clipping'&&$method==='GET')self::index();
        if(preg_match('#^/admin/manual-clipping/([0-9a-f-]{36})/marks/([0-9a-f-]{36})/update$#i',$path,$m)&&$method==='POST')
            self::updateMark(strtolower($m[1]),strtolower($m[2]));
        if(preg_match('#^/admin/manual-clipping/([0-9a-f-]{36})/marks/([0-9a-f-]{36})/(activate|hide)$#i',$path,$m)&&$method==='POST')
            self::toggleMark(strtolower($m[1]),strtolower($m[2]),$m[3]);
        if(preg_match('#^/admin/manual-clipping/([0-9a-f-]{36})/marks/([0-9a-f-]{36})/remove$#i',$path,$m)&&$method==='POST')
            self::removeMark(strtolower($m[1]),strtolower($m[2]));
        if(preg_match('#^/admin/manual-clipping/([0-9a-f-]{36})/marks/([0-9a-f-]{36})/(publish-official|withdraw-official)$#i',$path,$m)&&$method==='POST')
            self::officialMark(strtolower($m[1]),strtolower($m[2]),$m[3]);
        if(preg_match('#^/admin/manual-clipping/([0-9a-f-]{36})/import/([0-9a-z]{18})$#i',$path,$m)&&$method==='POST')
            self::importClip(strtolower($m[1]),strtolower($m[2]));
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
        $tb=TenantData::tenantBin();
        $publications=Store::all(
            'SELECT publication_guid,name FROM ep_publications
             WHERE tenant_guid=? AND status="ACTIVE" ORDER BY name LIMIT 200',[$tb]
        );
        $publication=trim((string)($_GET['publication']??''));
        $available=[];
        foreach($publications as $pub){
            $available[]=Uuid::fromBinary($pub['publication_guid']);
        }
        if(!Uuid::isValid($publication)||!in_array(strtolower($publication),$available,true)){
            $publication='';
        }
        $date=trim((string)($_GET['date']??''));
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)){
            $date='';
        }else{
            [$yy,$mm,$dd]=array_map('intval',explode('-',$date));
            if(!checkdate($mm,$dd,$yy))$date='';
        }
        $requestedPage=filter_var($_GET['p']??1,FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1,'max_range'=>500]]);
        $currentPage=$requestedPage===false?1:$requestedPage;
        $where='WHERE e.tenant_guid=? AND e.status NOT IN ("TRASHED")';
        $params=[$tb];
        if($publication!==''){
            $where.=' AND e.publication_guid=?';
            $params[]=Uuid::toBinary($publication);
        }
        if($date!==''){
            $where.=' AND e.edition_date=?';
            $params[]=$date;
        }
        // Page selection is edition-first: LIMIT applies to complete editions, not
        // individual pages. One summary query and one bounded page query only.
        $editions=Store::all(
            'SELECT e.edition_guid,e.title edition_title,e.edition_date,
                    pub.name publication_name,pub.publication_guid,
                    COUNT(p.page_guid) ready_pages
             FROM ep_editions e
             JOIN ep_publications pub ON pub.tenant_guid=e.tenant_guid
                 AND pub.publication_guid=e.publication_guid
             JOIN ep_pages p ON p.tenant_guid=e.tenant_guid
                 AND p.edition_guid=e.edition_guid
             JOIN ep_page_assets preview ON preview.tenant_guid=p.tenant_guid
                 AND preview.page_guid=p.page_guid AND preview.asset_type="PREVIEW"
             JOIN ep_page_assets hires ON hires.tenant_guid=p.tenant_guid
                 AND hires.page_guid=p.page_guid AND hires.asset_type="HIGHRES"
             '.$where.'
             GROUP BY e.edition_guid,e.title,e.edition_date,e.created_at,
                      pub.name,pub.publication_guid
             ORDER BY e.edition_date DESC,e.created_at DESC,e.edition_guid DESC
             LIMIT 21 OFFSET '.(($currentPage-1)*20),$params
        );
        $hasNext=count($editions)>20;
        $editions=array_slice($editions,0,20);
        $selected=(string)($_GET['edition']??'');
        $chosen=null;
        foreach($editions as $edition){
            if($selected!==''&&
               strtolower((string)Uuid::fromBinary($edition['edition_guid']))===strtolower($selected)){
                $chosen=$edition;break;
            }
        }
        if($chosen===null)$chosen=$editions[0]??null;
        $pages=[];
        if($chosen!==null){
            $pages=Store::all(
                'SELECT p.page_guid,p.page_number,p.label FROM ep_pages p
                 JOIN ep_page_assets preview ON preview.tenant_guid=p.tenant_guid
                      AND preview.page_guid=p.page_guid AND preview.asset_type="PREVIEW"
                 JOIN ep_page_assets hires ON hires.tenant_guid=p.tenant_guid
                      AND hires.page_guid=p.page_guid AND hires.asset_type="HIGHRES"
                 WHERE p.tenant_guid=? AND p.edition_guid=?
                 ORDER BY p.page_number ASC LIMIT 500',
                [$tb,$chosen['edition_guid']]
            );
        }
        View::layout('Clip marking',ManualClippingView::index(
            $editions,$pages,$publications,$chosen,[
                'publication'=>$publication,'date'=>$date,'page'=>$currentPage,
                'has_next'=>$hasNext,
            ]
        ),[
            'brand'=>(string)RequestContext::get('tenant_name','ePaper SaaS'),'admin'=>true,
        ]);
    }

    private static function detail(string $guid,?string $error=null): never
    {
        $page=self::page($guid);
        if(!$page){http_response_code(404);exit('Page not found');}
        $actor=(string)(Auth::user()['user_guid']??'');
        $role=strtoupper((string)(Auth::currentMembership()['role_key']??''));
        $clips=[];
        if(Uuid::isValid($actor)){
            $clips=Store::all('SELECT clip_slug,title,created_at FROM ep_clips
                WHERE tenant_guid=? AND page_guid=? AND platform_user_guid=? AND expires_at IS NULL
                ORDER BY created_at DESC LIMIT 24',
                [TenantData::tenantBin(),Uuid::toBinary($guid),Uuid::toBinary($actor)]);
        }
        $marks=[];$schemaReady=true;
        try{$marks=ManualClipMarking::forAdmin($guid);}
        catch(\Throwable){$schemaReady=false;}
        $tb=TenantData::tenantBin();
        
        $prev=Store::one(
            'SELECT p.page_guid,p.page_number FROM ep_pages p
             JOIN ep_page_assets prv ON prv.tenant_guid=p.tenant_guid AND prv.page_guid=p.page_guid AND prv.asset_type="PREVIEW"
             JOIN ep_page_assets hi ON hi.tenant_guid=p.tenant_guid AND hi.page_guid=p.page_guid AND hi.asset_type="HIGHRES"
             WHERE p.tenant_guid=? AND p.edition_guid=? AND p.page_number<?
             ORDER BY p.page_number DESC LIMIT 1',
            [$tb,$page['edition_guid'],(int)$page['page_number']]
        );
        $next=Store::one(
            'SELECT p.page_guid,p.page_number FROM ep_pages p
             JOIN ep_page_assets prv ON prv.tenant_guid=p.tenant_guid AND prv.page_guid=p.page_guid AND prv.asset_type="PREVIEW"
             JOIN ep_page_assets hi ON hi.tenant_guid=p.tenant_guid AND hi.page_guid=p.page_guid AND hi.asset_type="HIGHRES"
             WHERE p.tenant_guid=? AND p.edition_guid=? AND p.page_number>?
             ORDER BY p.page_number ASC LIMIT 1',
            [$tb,$page['edition_guid'],(int)$page['page_number']]
        );
        $created=(string)($_GET['created']??'');
        $notice=preg_match('/^[a-f0-9]{18}$/D',$created)?'/c/'.$created:'';
        View::layout('Clip marking · Page '.(int)$page['page_number'],
            ManualClippingView::detail($page,$guid,$clips,$notice,$error,$marks,$schemaReady,
                $actor,$role,[
                    'previous'=>$prev?['guid'=>Uuid::fromBinary($prev['page_guid']),'number'=>(int)$prev['page_number']]:null,
                    'next'=>$next?['guid'=>Uuid::fromBinary($next['page_guid']),'number'=>(int)$next['page_number']]:null,
                ]),
            ['brand'=>(string)RequestContext::get('tenant_name','ePaper SaaS'),'admin'=>true]);
    }

    private static function updateMark(string $pageGuid,string $markGuid): never
    {
        self::limitActions();
        try{
            ManualClipMarking::update($pageGuid,$markGuid,$_POST,(string)($_POST['title']??''));
            Http::redirect('/admin/manual-clipping/'.$pageGuid.'?mark=updated');
        }catch(\InvalidArgumentException $e){
            http_response_code(422);self::detail($pageGuid,$e->getMessage());
        }catch(\Throwable $e){
            http_response_code(422);self::detail($pageGuid,'Outline update failed: '.$e->getMessage());
        }
    }

    private static function toggleMark(string $pageGuid,string $markGuid,string $action): never
    {
        self::limitActions();
        try{
            ManualClipMarking::change($pageGuid,$markGuid,$action);
            Http::redirect('/admin/manual-clipping/'.$pageGuid.'?mark='.($action==='hide'?'hidden':'activated'));
        }catch(\Throwable $e){
            http_response_code(422);self::detail($pageGuid,'Mark status could not be changed: '.$e->getMessage());
        }
    }

    private static function removeMark(string $pageGuid,string $markGuid): never
    {
        self::limitActions();
        try{
            ManualClipMarking::remove($pageGuid,$markGuid);
            Http::redirect('/admin/manual-clipping/'.$pageGuid.'?mark=removed');
        }catch(\Throwable $e){
            http_response_code(422);self::detail($pageGuid,'Marked article could not be removed: '.$e->getMessage());
        }
    }

    private static function officialMark(string $pageGuid,string $markGuid,string $action): never
    {
        self::limitActions();
        try{
            if($action==='publish-official')
                OfficialClipService::approve($pageGuid,$markGuid);
            else OfficialClipService::withdraw($pageGuid,$markGuid);
            Http::redirect('/admin/manual-clipping/'.$pageGuid.'?mark='.
                ($action==='publish-official'?'official-published':'official-withdrawn'));
        }catch(\Throwable $e){
            http_response_code(422);
            self::detail($pageGuid,'Official clipping action failed: '.$e->getMessage());
        }
    }

    private static function importClip(string $pageGuid,string $slug): never
    {
        self::limitActions();
        try{
            ManualClipMarking::importPrivateClip($pageGuid,$slug);
            Http::redirect('/admin/manual-clipping/'.$pageGuid.'?mark=imported');
        }catch(\Throwable $e){
            http_response_code(422);self::detail($pageGuid,'Clipping could not be marked: '.$e->getMessage());
        }
    }

    private static function limitActions(): void
    {
        $actor=(string)(Auth::user()['user_guid']??'');
        if(!Uuid::isValid($actor)){http_response_code(403);exit('Editor identity required');}
        if(!RateLimiter::allow('clip-mark:'.RequestContext::tenantGuid().':'.$actor,30,60)){
            http_response_code(429);exit('Too many clipping actions. Please retry shortly.');
        }
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
            if((string)($_POST['action']??'mark')==='private'){
                $clip=ClipService::createEditorial(
                    $guid,$box['x'],$box['y'],$box['w'],$box['h'],$title);
                Http::redirect('/admin/manual-clipping/'.$guid.'?created='.rawurlencode($clip['slug']));
            }
            $markGuid=ManualClipMarking::add($guid,$box,$title);
            Http::redirect('/admin/manual-clipping/'.$guid.'?mark=saved');
        }catch(\InvalidArgumentException $e){
            http_response_code(422);self::detail($guid,$e->getMessage());
        }catch(\Throwable $e){
            Audit::log('clip.manual_failed','page',$guid,'FAILED',['message'=>$e->getMessage()]);
            http_response_code(503);self::detail($guid,'Clipping could not be saved. Check page media and image processing.');
        }
    }
}
