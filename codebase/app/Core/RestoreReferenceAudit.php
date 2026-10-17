<?php
declare(strict_types=1);
namespace EpaperSaas\Core;

/** Pure, read-only safety check for relative storage paths persisted in the DB. */
final class RestoreReferenceAudit
{
    public static function exists(string $storageRoot, string $relative): bool
    {
        $root=realpath($storageRoot);
        if(!$root||!is_dir($root))return false;
        if($relative===''||str_starts_with($relative,'/')||str_contains($relative,'\\')
           ||preg_match('/[\x00-\x1f]/',$relative)
           ||preg_match('#(^|/)(?:\.|\.\.)(?:/|$)#',$relative)
           ||str_contains($relative,'//'))return false;
        $candidate=realpath($root.'/'.$relative);
        if(!$candidate||!str_starts_with($candidate,rtrim($root,'/').'/'))return false;
        return is_file($candidate)&&is_readable($candidate);
    }
}
