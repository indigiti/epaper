<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Auth,Http,RequestContext,Store,Uuid};

/**
 * Pre-marked article regions are geometry only. No image is created on save.
 * The existing /api/v1/clips creates an image only when a reader elects to do so.
 */
final class ManualClipMarking
{
    private const ROLES=['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR'];
    private const UNTITLED_ARTICLE='Untitled article';

    /**
     * Editorial area markings need valid geometry, not a headline.
     * Keep a non-empty display label because ep_clip_marks.title is NOT NULL.
     */
    public static function normalizeTitle(string $title): string
    {
        $title=trim($title);
        if(mb_strlen($title)>180)
            throw new \InvalidArgumentException('Headline cannot exceed 180 characters');
        return $title!==''?$title:self::UNTITLED_ARTICLE;
    }

    private static function actor(): array
    {
        $user=Auth::user();
        $role=strtoupper((string)(Auth::currentMembership()['role_key']??''));
        if(!$user||empty($user['user_guid'])||!Uuid::isValid((string)$user['user_guid'])
            ||!in_array($role,self::ROLES,true))
            throw new \RuntimeException('Editor permission is required');
        return [(string)$user['user_guid'],$role];
    }

    public static function add(string $pageGuid,array $box,string $title): string
    {
        [$actor,]=self::actor();
        if(!Uuid::isValid($pageGuid))throw new \InvalidArgumentException('Invalid newspaper page');
        $geometry=ManualClipGeometry::parse($box);
        $title=self::normalizeTitle($title);
        $tenant=TenantData::tenantBin();
        $ready=Store::one(
            'SELECT e.status FROM ep_pages p JOIN ep_editions e
             ON e.tenant_guid=p.tenant_guid AND e.edition_guid=p.edition_guid
             WHERE p.tenant_guid=? AND p.page_guid=? AND e.status NOT IN ("TRASHED") LIMIT 1',
            [$tenant,Uuid::toBinary($pageGuid)]
        );
        if(!$ready)throw new \RuntimeException('The selected newspaper page is unavailable');
        $count=(int)Store::value(
            'SELECT COUNT(*) FROM ep_clip_marks WHERE tenant_guid=? AND page_guid=? AND status="ACTIVE"',
            [$tenant,Uuid::toBinary($pageGuid)]
        );
        if($count>=80)throw new \RuntimeException('This page already has 80 active marks');
        $id=Uuid::v7();
        Store::exec('INSERT INTO ep_clip_marks
            (mark_guid,tenant_guid,page_guid,platform_user_guid,title,x,y,width,height,status,created_at,updated_at)
            VALUES(?,?,?,?,?,?,?,?,?,"ACTIVE",NOW(6),NOW(6))',
            [Uuid::toBinary($id),$tenant,Uuid::toBinary($pageGuid),Uuid::toBinary($actor),
             $title,$geometry['x'],$geometry['y'],$geometry['w'],$geometry['h']]
        );
        Audit::log('clip_mark.created','clip_mark',$id,'SUCCESS',
            ['page_guid'=>$pageGuid,'edition_status'=>$ready['status'],'no_crop_generated'=>true]);
        return $id;
    }

    /** Edit a saved rectangle; publisher admins can correct other editors' work. */
    public static function update(string $pageGuid,string $markGuid,array $input,string $title): void
    {
        [$actor,$role]=self::actor();
        if(!Uuid::isValid($pageGuid)||!Uuid::isValid($markGuid))
            throw new \InvalidArgumentException('Invalid outline');
        $box=ManualClipGeometry::parse($input);
        $title=self::normalizeTitle($title);
        $tenant=TenantData::tenantBin();
        $row=Store::one(
            'SELECT platform_user_guid,status FROM ep_clip_marks
             WHERE tenant_guid=? AND page_guid=? AND mark_guid=? LIMIT 1',
            [$tenant,Uuid::toBinary($pageGuid),Uuid::toBinary($markGuid)]
        );
        if(!$row||!in_array((string)$row['status'],['ACTIVE','HIDDEN'],true))
            throw new \RuntimeException('Article outline not found');
        if($role==='EDITOR'&&
            !hash_equals((string)$row['platform_user_guid'],Uuid::toBinary($actor)))
            throw new \RuntimeException('Editors may edit only their own outlines');
        Store::exec(
            'UPDATE ep_clip_marks SET title=?,x=?,y=?,width=?,height=?,updated_at=NOW(6)
             WHERE tenant_guid=? AND page_guid=? AND mark_guid=? AND status IN ("ACTIVE","HIDDEN")',
            [$title,$box['x'],$box['y'],$box['w'],$box['h'],
             $tenant,Uuid::toBinary($pageGuid),Uuid::toBinary($markGuid)]
        );
        Audit::log('clip_mark.updated','clip_mark',$markGuid,'SUCCESS',
            ['page_guid'=>$pageGuid,'image_created'=>false]);
    }

    public static function change(string $pageGuid,string $markGuid,string $mode): void
    {
        [$actor,$role]=self::actor();
        if(!Uuid::isValid($pageGuid)||!Uuid::isValid($markGuid)||!in_array($mode,['activate','hide'],true))
            throw new \InvalidArgumentException('Invalid marking action');
        $tenant=TenantData::tenantBin();
        $mark=Store::one(
            'SELECT platform_user_guid,status FROM ep_clip_marks WHERE tenant_guid=? AND page_guid=? AND mark_guid=?',
            [$tenant,Uuid::toBinary($pageGuid),Uuid::toBinary($markGuid)]
        );
        if(!$mark||!in_array((string)$mark['status'],['ACTIVE','HIDDEN'],true))
            throw new \RuntimeException('Marking not found');
        if($role==='EDITOR'&&
            !hash_equals((string)$mark['platform_user_guid'],Uuid::toBinary($actor)))
            throw new \RuntimeException('Editors may change only their own markings');
        $newStatus=$mode==='hide'?'HIDDEN':'ACTIVE';
        Store::exec('UPDATE ep_clip_marks SET status=?,updated_at=NOW(6)
                     WHERE tenant_guid=? AND page_guid=? AND mark_guid=? AND status IN ("ACTIVE","HIDDEN")',
            [$newStatus,$tenant,Uuid::toBinary($pageGuid),Uuid::toBinary($markGuid)]);
        Audit::log('clip_mark.'.$mode,'clip_mark',$markGuid,'SUCCESS',['page_guid'=>$pageGuid]);
    }

    /**
     * Soft-remove a marked region so it cannot appear in the editor or Reader.
     * Preserve the row and audit history; deletion does not touch generated clips.
     */
    public static function remove(string $pageGuid,string $markGuid): void
    {
        [$actor,$role]=self::actor();
        if(!Uuid::isValid($pageGuid)||!Uuid::isValid($markGuid))
            throw new \InvalidArgumentException('Invalid outline');
        $tenant=TenantData::tenantBin();
        $mark=Store::one(
            'SELECT platform_user_guid,status FROM ep_clip_marks
             WHERE tenant_guid=? AND page_guid=? AND mark_guid=? LIMIT 1',
            [$tenant,Uuid::toBinary($pageGuid),Uuid::toBinary($markGuid)]
        );
        if(!$mark||!in_array((string)$mark['status'],['ACTIVE','HIDDEN'],true))
            throw new \RuntimeException('Marked article not found');
        if($role==='EDITOR'&&
            !hash_equals((string)$mark['platform_user_guid'],Uuid::toBinary($actor)))
            throw new \RuntimeException('Editors may remove only their own outlines');
        $changed=Store::exec(
            'UPDATE ep_clip_marks SET status="DELETED",updated_at=NOW(6)
             WHERE tenant_guid=? AND page_guid=? AND mark_guid=? AND status IN ("ACTIVE","HIDDEN")',
            [$tenant,Uuid::toBinary($pageGuid),Uuid::toBinary($markGuid)]
        );
        if($changed!==1)throw new \RuntimeException('Marked article was already removed');
        Audit::log('clip_mark.removed','clip_mark',$markGuid,'SUCCESS',
            ['page_guid'=>$pageGuid,'no_crop_generated'=>true]);
    }

    public static function importPrivateClip(string $pageGuid,string $slug): string
    {
        [$actor,$role]=self::actor();
        if(!Uuid::isValid($pageGuid)||!preg_match('/^[a-z0-9]{18}$/D',$slug))
            throw new \InvalidArgumentException('Invalid clipping');
        $clip=Store::one(
            'SELECT c.title,c.x,c.y,c.width,c.height,c.platform_user_guid FROM ep_clips c
             WHERE c.tenant_guid=? AND c.page_guid=? AND c.clip_slug=?
               AND c.expires_at IS NULL LIMIT 1',
            [TenantData::tenantBin(),Uuid::toBinary($pageGuid),$slug]
        );
        if(!$clip)throw new \RuntimeException('Private clipping not found');
        if($role==='EDITOR'&&
            (!is_string($clip['platform_user_guid'])||
             !hash_equals($clip['platform_user_guid'],Uuid::toBinary($actor))))
            throw new \RuntimeException('Editors may import only their own clippings');
        return self::add($pageGuid,
            ['x'=>$clip['x'],'y'=>$clip['y'],'w'=>$clip['width'],'h'=>$clip['height']],
            trim((string)$clip['title'])?:'News clipping');
    }

    public static function forAdmin(string $pageGuid): array
    {
        $tenant=TenantData::tenantBin();
        $rows=Store::all(
            'SELECT mark_guid,platform_user_guid,title,x,y,width,height,status,created_at
             FROM ep_clip_marks WHERE tenant_guid=? AND page_guid=? AND status IN ("ACTIVE","HIDDEN")
             ORDER BY created_at DESC LIMIT 120',
            [$tenant,Uuid::toBinary($pageGuid)]
        );
        $official=OfficialClipService::urlsForPage($pageGuid);
        foreach($rows as &$r){
            $r['mark_guid']=Uuid::fromBinary($r['mark_guid']);
            $r['official_url']=$official[$r['mark_guid']]??null;
        }unset($r);
        return $rows;
    }

    public static function publicForPage(string $pageGuid): array
    {
        if(!Uuid::isValid($pageGuid))return [];
        $tenant=TenantData::tenantBin();
        $rows=Store::all(
            'SELECT m.mark_guid,m.title,m.x,m.y,m.width,m.height
             FROM ep_clip_marks m
             JOIN ep_pages p ON p.tenant_guid=m.tenant_guid AND p.page_guid=m.page_guid
             JOIN ep_editions e ON e.tenant_guid=p.tenant_guid AND e.edition_guid=p.edition_guid
             WHERE m.tenant_guid=? AND m.page_guid=? AND m.status="ACTIVE"
               AND e.status="PUBLISHED"
             ORDER BY m.created_at ASC LIMIT 80',
            [$tenant,Uuid::toBinary($pageGuid)]
        );
        $out=[];
        $official=OfficialClipService::urlsForPage($pageGuid);
        foreach($rows as $r){
            $markGuid=Uuid::fromBinary($r['mark_guid']);
            $x=(float)$r['x'];$y=(float)$r['y'];$w=(float)$r['width'];$h=(float)$r['height'];
            if($x<0||$y<0||$w<.02||$h<.02||$x+$w>1.00001||$y+$h>1.00001)continue;
            $out[]=['mark_guid'=>$markGuid,
                'title'=>(string)$r['title'],'x'=>$x,'y'=>$y,'w'=>$w,'h'=>$h,
                'official_url'=>$official[$markGuid]??null];
        }
        return $out;
    }

    /**
     * Never trust a Reader-supplied mark GUID without checking tenant, publication
     * state, page ownership and the exact saved geometry on the server.
     */
    public static function validateClipSource(string $pageGuid,string $markGuid,array $geometry): void
    {
        if(!Uuid::isValid($pageGuid)||!Uuid::isValid($markGuid))
            throw new \InvalidArgumentException('Invalid article marking');
        $mark=Store::one(
            'SELECT m.x,m.y,m.width,m.height FROM ep_clip_marks m
             JOIN ep_pages p ON p.tenant_guid=m.tenant_guid AND p.page_guid=m.page_guid
             JOIN ep_editions e ON e.tenant_guid=p.tenant_guid AND e.edition_guid=p.edition_guid
             WHERE m.tenant_guid=? AND m.page_guid=? AND m.mark_guid=?
               AND m.status="ACTIVE" AND e.status="PUBLISHED" LIMIT 1',
            [TenantData::tenantBin(),Uuid::toBinary($pageGuid),Uuid::toBinary($markGuid)]
        );
        if(!$mark)throw new \InvalidArgumentException('This article marking is no longer available');
        foreach(['x'=>'x','y'=>'y','w'=>'width','h'=>'height'] as $input=>$column){
            if(abs((float)$mark[$column]-(float)($geometry[$input]??-1))>0.00000015)
                throw new \InvalidArgumentException('Article marking coordinates have changed. Reload this page.');
        }
    }

    public static function respond(string $pageGuid): never
    {
        Http::privateNoStore();
        if(!Uuid::isValid($pageGuid))Http::json(['ok'=>false,'code'=>'INVALID_PAGE'],422);
        try{
            Http::json(['ok'=>true,'page_guid'=>$pageGuid,'marks'=>self::publicForPage($pageGuid)]);
        }catch(\Throwable){
            Http::json(['ok'=>false,'code'=>'MARKING_SCHEMA_NOT_READY','marks'=>[]],503);
        }
    }
}
