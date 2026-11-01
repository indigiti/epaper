<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

use EpaperSaas\Core\{RequestContext,Store,Uuid};

/**
 * Bounded, read-only tenant utilization snapshot.
 *
 * A MariaDB/InnoDB tablespace is shared by many tenants: its DATA_LENGTH and
 * INDEX_LENGTH cannot be attributed accurately to one tenant. Never present
 * shared database bytes as this publisher's private allocation.
 */
final class TenantResourceReport
{
    public static function snapshot(): array
    {
        return MultiLayerCache::remember('tenant-utilization:v1',300,900,
            static fn():array=>self::measure());
    }

    private static function measure(): array
    {
        $tenant=RequestContext::tenantGuid();
        $bin=Uuid::toBinary($tenant);
        $row=Store::one(
            'SELECT '
            .'(SELECT COUNT(*) FROM ep_editions WHERE tenant_guid=?) editions, '
            .'(SELECT COUNT(*) FROM ep_pages WHERE tenant_guid=?) pages, '
            .'(SELECT COUNT(*) FROM ep_page_assets WHERE tenant_guid=?) assets, '
            .'(SELECT COUNT(*) FROM ep_page_ocr WHERE tenant_guid=?) ocr_pages, '
            .'(SELECT COUNT(*) FROM ep_clips WHERE tenant_guid=?) clips, '
            .'(SELECT COUNT(*) FROM ep_processing_jobs WHERE tenant_guid=?) jobs, '
            .'(SELECT COUNT(*) FROM ep_local_mobile_accounts WHERE tenant_guid=?) mobile_readers',
            [$bin,$bin,$bin,$bin,$bin,$bin,$bin]
        )?:[];
        // This is the sum of file-byte metadata, not database consumption.
        $assetRows=Store::all('SELECT asset_type,COUNT(*) asset_count,'
            .'COALESCE(SUM(byte_size),0) asset_bytes '
            .'FROM ep_page_assets WHERE tenant_guid=? GROUP BY asset_type ORDER BY asset_bytes DESC',
            [$bin]);
        $assetBytes=0;
        foreach($assetRows as $asset)$assetBytes+=(int)($asset['asset_bytes']??0);

        $root=dirname(__DIR__,2).'/storage';
        $disk=[];$diskSum=0;$complete=true;
        foreach(['Original PDF uploads'=>'uploads','Rendered page media'=>'media','Reader clippings'=>'clips'] as $label=>$folder){
            $part=self::scanDirectory($root.'/'.$folder.'/'.$tenant);
            $disk[$label]=$part;
            $diskSum+=$part['bytes'];
            if(!$part['complete'])$complete=false;
        }
        $sharedDbBytes=null;
        try{
            $v=Store::value('SELECT SUM(data_length+index_length) FROM information_schema.tables '
                .'WHERE table_schema=DATABASE() AND table_name LIKE ?',
                ['ep\_%']);
            $sharedDbBytes=$v===null?null:max(0,(int)$v);
        }catch(\Throwable){
            // Hosted database user may not have INFORMATION_SCHEMA visibility.
        }
        return [
            'tenant_guid'=>$tenant,
            'generated_at'=>gmdate('c'),
            'counts'=>$row,
            'asset_types'=>$assetRows,
            'referenced_asset_bytes'=>$assetBytes,
            'local_disk'=>$disk,
            'local_disk_bytes'=>$diskSum,
            'local_disk_complete'=>$complete,
            'shared_db_physical_bytes'=>$sharedDbBytes,
            'db_tenant_physical_bytes'=>null,
        ];
    }

    /**
     * Files under tenant UUID directories only; no traversal of other tenants,
     * shared caches, symlink targets or unbounded filesystem walks.
     */
    public static function scanDirectory(string $directory,int $maxFiles=2500,float $maxSeconds=0.20): array
    {
        $result=['bytes'=>0,'files'=>0,'complete'=>true,'exists'=>is_dir($directory)];
        if(!$result['exists'])return $result;
        $deadline=microtime(true)+$maxSeconds;
        try{
            $iterator=new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory,\FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach($iterator as $file){
                if(microtime(true)>=$deadline || $result['files']>=$maxFiles){
                    $result['complete']=false;break;
                }
                if($file->isLink() || !$file->isFile())continue;
                $result['files']++;
                $result['bytes']+=max(0,(int)$file->getSize());
            }
        }catch(\Throwable){
            $result['complete']=false;
        }
        return $result;
    }
}
