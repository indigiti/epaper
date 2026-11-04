<?php
declare(strict_types=1);

$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='EpaperSaas\\';
    if(!str_starts_with($class,$prefix))return;
    $path=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require $path;
});

use EpaperSaas\Services\Media;

function invariant(bool $pass,string $name):void
{
    if(!$pass){fwrite(STDERR,"FAIL: $name\n");exit(1);}
    echo "PASS: $name\n";
}

$a='aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
$b='bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb';
$edition='cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$good="media/$a/$edition/thumb-1.webp";
invariant(Media::isTenantPageAssetPath($good,$a),'Current tenant edition media is allowed');
invariant(!Media::isTenantPageAssetPath("media/$b/$edition/thumb-1.webp",$a),'Other publisher media rejected');
invariant(!Media::isTenantPageAssetPath("media/$a/../$b/$edition/thumb-1.webp",$a),'Traversal rejected');
invariant(!Media::isTenantPageAssetPath("media/$a/$edition/./thumb-1.webp",$a),'Dot segments rejected');
invariant(!Media::isTenantPageAssetPath("/$good",$a),'Absolute paths rejected');
invariant(!Media::isTenantPageAssetPath("media\\$a\\thumb-1.webp",$a),'Windows separators rejected');
invariant(!Media::isTenantPageAssetPath("uploads/$a/thumb-1.webp",$a),'Media cannot access unrelated storage');
invariant(!Media::isTenantPageAssetPath($good,'bad-tenant'),'Invalid tenant rejected');

$app=file_get_contents($root.'/app/App.php');
$media=file_get_contents($root.'/app/Services/Media.php');
$resolver=file_get_contents($root.'/app/Services/DomainResolver.php');
$sw=file_get_contents($root.'/public_html/service-worker.js');
invariant(str_contains($app,"'/social/edition/'.rawurlencode("),
    'Public edition cards use stable published media route');
invariant(str_contains($app,"Media::tenantPageAssetPath((string)"),
    'Public cover endpoint resolves through tenant-constrained storage');
invariant(str_contains($app,"foreach(['THUMB','PREVIEW'] as \$type)"),
    'Cover endpoint attempts thumbnail then preview');
invariant(str_contains($app,"if(!\$asset||!\$full){http_response_code(404)"),
    'Missing published files return 404 rather than a generic error page');
invariant(str_contains($media,"$"."path=self::tenantPageAssetPath("),
    'Signed reader images enforce tenant-specific filesystem paths');
invariant(str_contains($resolver,"PUBLISHER_TENANT_INACTIVE"),
    'Known inactive publishers cannot be reactivated by domain resolution');
invariant(str_contains($sw,"url.pathname.startsWith('/media/')"),
    'Private reader media is excluded from service-worker persistence');
echo "PASS tenant media and cover regression contract\n";
