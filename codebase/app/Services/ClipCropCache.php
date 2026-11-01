<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

/**
 * Private, content-addressed cache for the UNBRANDED page crop.
 *
 * This is not a clip or a permission system: every reader still receives a
 * separate ep_clips record, slug, expiry, branding and analytics. The only
 * shared resource is immutable crop media stored outside the web document root.
 */
final class ClipCropCache
{
    private const VERSION='raw-crop-v1';
    private const RETAIN_SECONDS=1209600; // 14 days; clip records still expire in 7 days.

    public static function key(string $tenant,string $pageGuid,string $sourceHash,array $geometry): string
    {
        if(!preg_match('/^[a-f0-9-]{36}$/Di',$tenant) ||
           !preg_match('/^[a-f0-9-]{36}$/Di',$pageGuid) ||
           !preg_match('/^[a-f0-9]{64}$/D',$sourceHash))
            throw new \InvalidArgumentException('Invalid clip cache scope or source hash');
        foreach(['x','y','w','h'] as $field){
            if(!array_key_exists($field,$geometry)||!is_numeric($geometry[$field]))
                throw new \InvalidArgumentException('Invalid clip cache geometry');
        }
        return hash('sha256',json_encode([
            self::VERSION,strtolower($tenant),strtolower($pageGuid),
            strtolower($sourceHash),
            ...array_map(static fn($k)=>sprintf('%.7F',(float)$geometry[$k]),['x','y','w','h'])
        ],JSON_THROW_ON_ERROR));
    }

    /**
     * @param callable(string):void $render Creates a crop at a temporary .webp path.
     * @return array{hit:bool,mode:string}
     */
    public static function materialize(
        string $source,string $destination,string $tenant,string $pageGuid,
        array $geometry,callable $render
    ): array {
        if(!is_file($source)||!is_readable($source))
            throw new \RuntimeException('Clipping source image is unavailable');
        // Using actual content prevents stale reuse after page media is regenerated,
        // including replacements that accidentally retain a file name and mtime.
        $sourceHash=hash_file('sha256',$source);
        if(!is_string($sourceHash))throw new \RuntimeException('Cannot verify clipping source image');
        $key=self::key($tenant,$pageGuid,$sourceHash,$geometry);
        $root=dirname(__DIR__,2).'/storage/clip-crop-cache/'.strtolower($tenant);
        $shard=$root.'/'.substr($key,0,2);
        if(!is_dir($shard)&&!@mkdir($shard,0770,true)&&!is_dir($shard)){
            $render($destination);
            return ['hit'=>false,'mode'=>'uncached'];
        }
        $cache=$shard.'/'.$key.'.webp';
        $lock=@fopen($shard.'/.crop.lock','c');
        if(!$lock){
            $render($destination);
            return ['hit'=>false,'mode'=>'uncached'];
        }
        try{
            // The lock serializes identical crops across PHP-FPM workers.
            if(!flock($lock,LOCK_EX))throw new \RuntimeException('Crop cache lock unavailable');
            $hit=is_file($cache)&&filesize($cache)>0;
            if(!$hit){
                $tmp=$shard.'/'.$key.'.tmp-'.bin2hex(random_bytes(8)).'.webp';
                try{
                    $render($tmp);
                    if(!is_file($tmp)||filesize($tmp)<1)
                        throw new \RuntimeException('Clipping crop generation returned no image');
                    if(!@rename($tmp,$cache))
                        throw new \RuntimeException('Clipping cache write failed');
                }finally{
                    if(is_file($tmp))@unlink($tmp);
                }
            }
            // Hard links deduplicate physical bytes. Cloudways and hardened
            // PHP-FPM pools may disable link() entirely via disable_functions:
            // calling a disabled function causes a fatal Error, even with @.
            // Cache acceleration must never prevent a reader from clipping.
            $linked=false;
            if(function_exists('link')){
                try{$linked=@link($cache,$destination);}catch(\Throwable){$linked=false;}
            }
            if($linked){
                $mode='link';
            }else{
                $copied=false;
                if(function_exists('copy')){
                    try{$copied=@copy($cache,$destination);}catch(\Throwable){$copied=false;}
                }
                if($copied){
                    $mode='copy';
                }else{
                    // Restricted filesystems/functions must not block crop creation.
                    // Regenerate only this reader's destination; leave cache intact.
                    if(is_file($destination))@unlink($destination);
                    $render($destination);
                    if(!is_file($destination)||filesize($destination)<1)
                        throw new \RuntimeException('Could not materialize the clipping image');
                    $mode='render';
                }
            }
            @touch($cache); // Enforce sliding cache retention independent of clip expiry.
            // Incremental, bounded stale-cache cleanup. Never deletes reader clip
            // paths; disabled/erroring housekeeping does not block clip creation.
            if(random_int(1,48)===1){
                try{self::prune($tenant,160);}catch(\Throwable){}
            }
            return ['hit'=>$hit,'mode'=>$mode];
        }finally{
            @flock($lock,LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Explicitly bounded housekeeping, called by a future Storage & Backups
     * maintenance action. Never deletes per-reader clips or active user images.
     */
    public static function prune(string $tenant,int $limit=200): int
    {
        if(!preg_match('/^[a-f0-9-]{36}$/Di',$tenant))throw new \InvalidArgumentException('Invalid tenant');
        $root=dirname(__DIR__,2).'/storage/clip-crop-cache/'.strtolower($tenant);
        if(!is_dir($root))return 0;
        $limit=max(1,min(500,$limit));$removed=0;$checked=0;
        foreach(new \FilesystemIterator($root,\FilesystemIterator::SKIP_DOTS) as $shard){
            if(!$shard->isDir()||!preg_match('/^[a-f0-9]{2}$/D',$shard->getFilename()))continue;
            foreach(new \FilesystemIterator($shard->getPathname(),\FilesystemIterator::SKIP_DOTS) as $item){
                if(++$checked>$limit)return $removed;
                if(!$item->isFile()||!preg_match('/^[a-f0-9]{64}\.webp$/D',$item->getFilename()))continue;
                if($item->getMTime()>time()-self::RETAIN_SECONDS)continue;
                $lockFile=$shard->getPathname().'/.crop.lock';
                $lock=@fopen($lockFile,'c');
                if(!$lock)continue;
                try{
                    if(!flock($lock,LOCK_EX|LOCK_NB))continue;
                    clearstatcache(true,$item->getPathname());
                    if(is_file($item->getPathname())&&filemtime($item->getPathname())<=time()-self::RETAIN_SECONDS){
                        if(@unlink($item->getPathname()))$removed++;
                    }
                }finally{flock($lock,LOCK_UN);fclose($lock);}
            }
        }
        return $removed;
    }
}
