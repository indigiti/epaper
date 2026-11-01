<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Auth,RequestContext,Store,Uuid};

/**
 * Publisher-approved canonical links. PRIVATE clips are never promoted.
 * Approval is bound to the current published mark, page version and HIGHRES asset.
 */
final class OfficialClipService
{
    private const MANAGERS=['OWNER','ADMIN','PUBLISHER_ADMIN'];

    private static function authorize(): string
    {
        $actor=(string)(Auth::user()['user_guid']??'');
        $role=strtoupper((string)(Auth::currentMembership()['role_key']??''));
        if(!Uuid::isValid($actor)||!in_array($role,self::MANAGERS,true))
            throw new \RuntimeException('Publisher approval permission required');
        return $actor;
    }

    /**
     * One SQL gate controls links shown in the Reader and actual /c media access.
     * Any edit, hide, deleted mark, unpublished edition or replaced page asset
     * invalidates the old official link, even when its URL is known.
     */
    private const VALIDITY='
        o.status="ACTIVE"
        AND m.status="ACTIVE"
        AND e.status="PUBLISHED"
        AND c.visibility="OFFICIAL"
        AND c.expires_at IS NULL
        AND c.page_guid=o.page_guid
        AND c.mark_guid=o.mark_guid
        AND m.updated_at=o.mark_updated_at
        AND p.version=o.page_version
        AND a.asset_guid=o.asset_guid
        AND (CAST(a.checksum_sha256 AS BINARY) <=> CAST(o.asset_checksum AS BINARY))
    ';

    public static function urlsForPage(string $pageGuid): array
    {
        if(!SchemaDoctor::officialReady()||!Uuid::isValid($pageGuid))return [];
        $rows=Store::all('SELECT o.mark_guid,c.clip_slug
            FROM ep_official_clips o
            JOIN ep_clip_marks m ON m.tenant_guid=o.tenant_guid AND m.mark_guid=o.mark_guid AND m.page_guid=o.page_guid
            JOIN ep_pages p ON p.tenant_guid=o.tenant_guid AND p.page_guid=o.page_guid
            JOIN ep_editions e ON e.tenant_guid=p.tenant_guid AND e.edition_guid=p.edition_guid
            JOIN ep_page_assets a ON a.tenant_guid=p.tenant_guid AND a.page_guid=p.page_guid AND a.asset_type="HIGHRES"
            JOIN ep_clips c ON c.tenant_guid=o.tenant_guid AND c.clip_guid=o.clip_guid
            WHERE o.tenant_guid=? AND o.page_guid=? AND '.self::VALIDITY.'
            LIMIT 80',[TenantData::tenantBin(),Uuid::toBinary($pageGuid)]);
        $out=[];
        foreach($rows as $row){
            $id=Uuid::fromBinary($row['mark_guid']);
            $slug=(string)$row['clip_slug'];
            if(preg_match('/^[a-f0-9]{18}$/D',$slug))$out[$id]='/c/'.$slug;
        }
        return $out;
    }

    public static function mayView(array $clip): bool
    {
        if(!SchemaDoctor::officialReady()||
           !Uuid::isValid((string)($clip['clip_guid']??''))||
           !Uuid::isValid((string)($clip['mark_guid']??''))||
           !Uuid::isValid((string)($clip['page_guid']??'')))return false;
        return (bool)Store::one('SELECT 1
            FROM ep_official_clips o
            JOIN ep_clip_marks m ON m.tenant_guid=o.tenant_guid AND m.mark_guid=o.mark_guid AND m.page_guid=o.page_guid
            JOIN ep_pages p ON p.tenant_guid=o.tenant_guid AND p.page_guid=o.page_guid
            JOIN ep_editions e ON e.tenant_guid=p.tenant_guid AND e.edition_guid=p.edition_guid
            JOIN ep_page_assets a ON a.tenant_guid=p.tenant_guid AND a.page_guid=p.page_guid AND a.asset_type="HIGHRES"
            JOIN ep_clips c ON c.tenant_guid=o.tenant_guid AND c.clip_guid=o.clip_guid
            WHERE o.tenant_guid=? AND o.mark_guid=? AND o.page_guid=? AND o.clip_guid=?
              AND '.self::VALIDITY.' LIMIT 1',[
                TenantData::tenantBin(),Uuid::toBinary($clip['mark_guid']),
                Uuid::toBinary($clip['page_guid']),Uuid::toBinary($clip['clip_guid'])
            ]);
    }

    /** Publish once for this approved geometry; concurrent publishers use a tenant/mark advisory lock. */
    public static function approve(string $pageGuid,string $markGuid): string
    {
        $actor=self::authorize();
        if(!SchemaDoctor::officialReady()||!SchemaDoctor::clipReuseReady())
            throw new \RuntimeException('Publisher clipping schema needs repair in Upgrade Center');
        if(!Uuid::isValid($pageGuid)||!Uuid::isValid($markGuid))
            throw new \InvalidArgumentException('Invalid article outline');

        $tb=TenantData::tenantBin();
        $lockName='epdi:official:'.substr(hash('sha256',RequestContext::tenantGuid().':'.$markGuid),0,32);
        if((int)Store::value('SELECT GET_LOCK(?,10)',[$lockName])!==1)
            throw new \RuntimeException('Another approval is processing this article');
        try{
            $existing=self::urlsForPage($pageGuid);
            if(isset($existing[$markGuid]))return $existing[$markGuid];
            $mark=Store::one('SELECT m.title,m.x,m.y,m.width,m.height,m.updated_at,
                    p.version page_version,e.status edition_status,
                    a.asset_guid,a.checksum_sha256
                FROM ep_clip_marks m
                JOIN ep_pages p ON p.tenant_guid=m.tenant_guid AND p.page_guid=m.page_guid
                JOIN ep_editions e ON e.tenant_guid=p.tenant_guid AND e.edition_guid=p.edition_guid
                JOIN ep_page_assets a ON a.tenant_guid=p.tenant_guid AND a.page_guid=p.page_guid AND a.asset_type="HIGHRES"
                WHERE m.tenant_guid=? AND m.page_guid=? AND m.mark_guid=?
                  AND m.status="ACTIVE" AND e.status="PUBLISHED" LIMIT 1',
                [$tb,Uuid::toBinary($pageGuid),Uuid::toBinary($markGuid)]);
            if(!$mark)throw new \RuntimeException('Publish the edition and activate its article outline first');
            $clip=ClipService::createOfficial($pageGuid,$markGuid,
                (float)$mark['x'],(float)$mark['y'],
                (float)$mark['width'],(float)$mark['height'],(string)$mark['title']);
            Store::exec('INSERT INTO ep_official_clips
                  (tenant_guid,mark_guid,page_guid,clip_guid,asset_guid,asset_checksum,
                   page_version,mark_updated_at,status,approved_by_user_guid,approved_at,updated_at)
                  VALUES(?,?,?,?,?,?,?,?, "ACTIVE",?,NOW(6),NOW(6))
                  ON DUPLICATE KEY UPDATE
                    page_guid=VALUES(page_guid),clip_guid=VALUES(clip_guid),
                    asset_guid=VALUES(asset_guid),asset_checksum=VALUES(asset_checksum),
                    page_version=VALUES(page_version),mark_updated_at=VALUES(mark_updated_at),
                    status="ACTIVE",approved_by_user_guid=VALUES(approved_by_user_guid),
                    approved_at=NOW(6),updated_at=NOW(6)',[
                       $tb,Uuid::toBinary($markGuid),Uuid::toBinary($pageGuid),
                       Uuid::toBinary($clip['clip_guid']),$mark['asset_guid'],$mark['checksum_sha256'],
                       $mark['page_version'],$mark['updated_at'],Uuid::toBinary($actor)
                  ]);
            // Shared formats are generated once by the approving publisher, so
            // anonymous readers never trigger expensive image transformations.
            $official=ClipService::bySlug($clip['slug']);
            $prepared=0;$failed=[];
            if($official&&self::mayView($official)){
                foreach(ClipService::formats() as $format=>$definition){
                    try{
                        $out=ClipService::prepareFormat($official,$format);
                        if(!empty($out['ready']))$prepared++;
                    }catch(\Throwable $e){
                        $failed[]=$format;
                    }
                }
            }
            Audit::log('clip.official_approved','clip_mark',$markGuid,'SUCCESS',
                ['page_guid'=>$pageGuid,'clip_guid'=>$clip['clip_guid'],'official_url'=>$clip['url'],
                 'formats_ready'=>$prepared,'formats_failed'=>$failed]);
            return $clip['url'];
        }finally{
            try{Store::value('SELECT RELEASE_LOCK(?)',[$lockName]);}catch(\Throwable){}
        }
    }

    public static function withdraw(string $pageGuid,string $markGuid): void
    {
        self::authorize();
        if(!SchemaDoctor::officialReady())throw new \RuntimeException('Official clipping schema unavailable');
        if(!Uuid::isValid($pageGuid)||!Uuid::isValid($markGuid))
            throw new \InvalidArgumentException('Invalid article outline');
        $lockName='epdi:official:'.substr(hash('sha256',RequestContext::tenantGuid().':'.$markGuid),0,32);
        if((int)Store::value('SELECT GET_LOCK(?,10)',[$lockName])!==1)
            throw new \RuntimeException('Another approval is processing this article');
        try{
            $changed=Store::exec('UPDATE ep_official_clips SET status="REVOKED",updated_at=NOW(6)
                WHERE tenant_guid=? AND page_guid=? AND mark_guid=? AND status="ACTIVE"',
                [TenantData::tenantBin(),Uuid::toBinary($pageGuid),Uuid::toBinary($markGuid)]);
            if($changed<1)throw new \RuntimeException('No active official clipping was found');
            Audit::log('clip.official_withdrawn','clip_mark',$markGuid,'SUCCESS',['page_guid'=>$pageGuid]);
        }finally{
            try{Store::value('SELECT RELEASE_LOCK(?)',[$lockName]);}catch(\Throwable){}
        }
    }
}
