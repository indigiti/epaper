<?php
declare(strict_types=1);
namespace EpaperSaas\Core;

/** Reject confidential runtime artifacts before they reach Git history. */
final class TrackedFilePolicy
{
    public static function prohibited(string $path): bool
    {
        $path=str_replace('\\','/',$path);
        $base=basename($path);
        if(preg_match('/^(?:\.env(?:\..+)?|installed\.lock|install\.token)$/i',$base))return true;
        if(preg_match('/\.(?:pem|p12|pfx|key)$/i',$base))return true;
        if(preg_match('/^(?:firebase-)?service-account(?:[^/]*)\.json$/i',$base))return true;
        if(preg_match('/^apply-[^/]*-hotfix\.php$/i',$base))return true;
        if(preg_match('#(^|/)config/(?:config|runtime)\.php$#i',$path))return true;
        return false;
    }
}
