<?php
declare(strict_types=1);
/**
 * Disposable filesystem regression: disk cache must respect the 15-second
 * control-plane TTL, rather than silently extending it to 30 seconds.
 */
require dirname(__DIR__).'/app/Services/DomainResolver.php';
use EpaperSaas\Services\DomainResolver;
$key='ttl-regression:'.bin2hex(random_bytes(12));
$pathMethod=new ReflectionMethod(DomainResolver::class,'fileCachePath');
$writeMethod=new ReflectionMethod(DomainResolver::class,'fileCacheSet');
$path=$pathMethod->invoke(null,$key);
try {
    $before=time();
    $writeMethod->invoke(null,$key,['status'=>'ACTIVE'],15);
    if (!is_file($path)) throw new RuntimeException('Cache file not written');
    $row=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    $expiry=(int)($row['expires_at']??0);
    if($expiry<$before+15||$expiry>time()+15)
        throw new RuntimeException('15-second cache TTL was extended or shortened unexpectedly');
    echo "PASS — on-disk cache TTL follows 15-second policy\n";
} finally {
    if(is_file($path)) unlink($path);
}
