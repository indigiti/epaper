<?php
declare(strict_types=1);
namespace EpaperSaas\Core;

/**
 * Read-only filesystem inventory for an isolated backup/restore rehearsal.
 *
 * Reports only counts, bytes and composite content digests; never usernames,
 * path names, file contents or credentials. Not a database restore test.
 */
final class RestoreInventory
{
    private const CATEGORIES=['media','uploads','clips','cache','logs'];

    public static function snapshot(string $storageRoot): array
    {
        $root=realpath($storageRoot);
        if($root===false||!is_dir($root))throw new \RuntimeException('Storage root does not exist');
        $categories=[];
        foreach(self::CATEGORIES as$category) {
            $directory=$root.'/'.$category;
            if(!is_dir($directory))throw new \RuntimeException('A required runtime directory is missing: '.$category);
            $paths=[];
            $iterator=new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory,\FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach($iterator as$entry) {
                if($entry->isLink())throw new \RuntimeException('Storage contains a symlink; manual review required');
                if(!$entry->isFile())continue;
                if(in_array($entry->getFilename(),['.keep','.gitkeep'],true))continue;
                $name=substr($entry->getPathname(),strlen($directory)+1);
                $paths[$name]=$entry->getPathname();
            }
            ksort($paths,SORT_STRING);
            $count=0;$bytes=0;$digest=hash_init('sha256');
            foreach($paths as$relative=>$path){
                $size=@filesize($path);
                $hash=@hash_file('sha256',$path);
                if($size===false||$hash===false)throw new \RuntimeException('Could not read one of the storage files');
                hash_update($digest,$relative."\0".$size."\0".$hash."\n");
                $count++;$bytes+=$size;
            }
            $categories[$category]=['files'=>$count,'bytes'=>$bytes,'digest'=>hash_final($digest)];
        }
        return ['schema'=>1,'categories'=>$categories];
    }

    /**
     * Compare authoritative backup vs restored staging; cache and logs vary
     * legitimately and are included as informational rather than blockers.
     */
    public static function compare(array $baseline,array $restored): array
    {
        if(($baseline['schema']??null)!==1||($restored['schema']??null)!==1)
            throw new \RuntimeException('Unknown inventory schema');
        $results=[];$pass=true;
        foreach(self::CATEGORIES as$category) {
            $a=$baseline['categories'][$category]??null;
            $b=$restored['categories'][$category]??null;
            $same=is_array($a)&&is_array($b)
                &&isset($a['files'],$a['bytes'],$a['digest'],$b['files'],$b['bytes'],$b['digest'])
                &&$a['files']===$b['files']&&$a['bytes']===$b['bytes']
                &&is_string($a['digest'])&&is_string($b['digest'])
                &&hash_equals($a['digest'],$b['digest']);
            $required=in_array($category,['media','uploads','clips'],true);
            if($required&&!$same)$pass=false;
            $results[$category]=['required'=>$required,'match'=>$same,
                'backup_files'=>$a['files']??null,'restored_files'=>$b['files']??null,
                'backup_bytes'=>$a['bytes']??null,'restored_bytes'=>$b['bytes']??null];
        }
        // Protect against treating an empty or merely skeletal source checkout
        // as proof that newspaper media have been restored.
        foreach(['media','uploads'] as$category)
            if((int)($baseline['categories'][$category]['files']??0)<1
                ||(int)($restored['categories'][$category]['files']??0)<1)$pass=false;
        return ['match'=>$pass,'categories'=>$results,
            'database_restore_verified'=>false,
            'cloudways_application_restore_verified'=>false,
            'frontend_ocr_and_clipping_verified'=>false];
    }
}
