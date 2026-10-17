<?php
declare(strict_types=1);
namespace EpaperSaas\Core;

use RuntimeException;
use ZipArchive;

/**
 * Examine ZIP backups without extracting anything or printing member filenames.
 * Verification here is archive structure only, never Cloudways restore approval.
 */
final class BackupZipPreflight
{
    public static function inspect(string $archive, bool $readAll=false): array
    {
        if(!class_exists(ZipArchive::class))throw new RuntimeException('PHP zip extension required');
        $file=realpath($archive);
        if($file===false||!is_file($file)||!is_readable($file))throw new RuntimeException('ZIP file cannot be read');
        $zip=new ZipArchive();
        $opened=$zip->open($file,ZipArchive::CHECKCONS);
        if($opened!==true)throw new RuntimeException('ZIP central directory check failed');
        $stats=['entries'=>0,'regular_files'=>0,'uncompressed_bytes'=>0,
            'media_files'=>0,'original_pdf_files'=>0,'clip_files'=>0,'branding_files'=>0,
            'database_export_candidates'=>0,'nested_archives'=>0,'application_files'=>0];
        $violations=0;$unreadable=0;$seen=[];
        try{
            if($zip->numFiles>150000)throw new RuntimeException('Too many ZIP entries; review archive out of band');
            for($i=0;$i<$zip->numFiles;$i++){
                $meta=$zip->statIndex($i);
                if(!is_array($meta)||!isset($meta['name'])){$violations++;continue;}
                $name=(string)$meta['name'];
                $stats['entries']++;
                if($name===''||str_contains($name,"\\0")||str_contains($name,'\\')
                    ||str_starts_with($name,'/')||preg_match('#^[a-zA-Z]:#',$name)
                    ||str_contains($name,'//')
                    ||in_array('..',explode('/',$name),true)
                    ||in_array('.',explode('/',$name),true)){$violations++;continue;}
                if(isset($seen[$name])){$violations++;continue;}
                $seen[$name]=true;
                $opsys=0;$attributes=0;
                if($zip->getExternalAttributesIndex($i,$opsys,$attributes)
                    &&($opsys===ZipArchive::OPSYS_UNIX)
                    &&((($attributes >> 16)&0170000)===0120000)){$violations++;continue;}
                if(str_ends_with($name,'/'))continue;
                $size=(int)($meta['size']??-1);
                $compressed=(int)($meta['comp_size']??-1);
                if($size<0||$compressed<0||$size>8*1024*1024*1024){$violations++;continue;}
                $stats['uncompressed_bytes']+=$size;
                if($stats['uncompressed_bytes']>64*1024*1024*1024){$violations++;break;}
                $stats['regular_files']++;
                $normalized='/'.$name;
                if(preg_match('#/storage/media/[^/].+#i',$normalized))$stats['media_files']++;
                if(preg_match('#/storage/uploads/[^/]+(?:/[^/]+)*/[^/]+\\.pdf$#i',$normalized)
                    ||preg_match('#/storage/uploads/[^/]+\\.pdf$#i',$normalized))$stats['original_pdf_files']++;
                if(preg_match('#/storage/clips/[^/].+#i',$normalized))$stats['clip_files']++;
                if(str_contains($normalized,'/public_html/uploads/branding/'))$stats['branding_files']++;
                if(preg_match('#\\.(?:sql|sql\\.gz|dump)$#i',$name))$stats['database_export_candidates']++;
                if(preg_match('#\\.(?:zip|tar|tar\\.gz|tgz)$#i',$name))$stats['nested_archives']++;
                if(preg_match('#/(?:app/bootstrap\\.php|public_html/index\\.php|composer\\.json)$#',$normalized))$stats['application_files']++;
                if($readAll){
                    $stream=$zip->getStream($name);
                    if(!is_resource($stream)){$unreadable++;continue;}
                    $read=0;
                    try{
                        while(!feof($stream)){
                            $chunk=fread($stream,1048576);
                            if($chunk===false){$unreadable++;break;}
                            if($chunk===''){if(!feof($stream))$unreadable++;break;}
                            $read+=strlen($chunk);
                            if($read>$size){$unreadable++;break;}
                        }
                        if($read!==$size)$unreadable++;
                    } finally{fclose($stream);}
                }
            }
        }finally{$zip->close();}
        return [
            'archive_structure_ok'=>$violations===0&&$unreadable===0,
            'checked_member_contents'=>$readAll,
            'suspicious_entries'=>$violations,
            'unreadable_entries'=>$unreadable,
            'counts'=>$stats,
            'coverage'=>[
                'published_media_present'=>$stats['media_files']>0,
                'original_pdfs_present'=>$stats['original_pdf_files']>0,
                'clippings_present'=>$stats['clip_files']>0,
                'application_files_present'=>$stats['application_files']>0,
                'possible_db_export_present'=>$stats['database_export_candidates']>0
            ],
            'restore_test_verified'=>false,
            'database_restore_verified'=>false,
            'production_deployment_approved'=>false
        ];
    }
}
