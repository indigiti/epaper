<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/PublicUploadsSafety.php';
use EpaperSaas\Core\PublicUploadsSafety;

if (PHP_SAPI !== 'cli') exit(2);
$root = sys_get_temp_dir().'/epdi-branding-fixture-'.bin2hex(random_bytes(7));
$app = $root.'/release';
$persist = $root.'/persist/public-uploads';
$wrong = $root.'/wrong';
$cases = 0; $fails = 0;
$assert = static function (bool $ok, string $name) use (&$cases, &$fails): void {
    $cases++;
    if (!$ok) { $fails++; fwrite(STDERR, "FAIL uploads preflight: ".$name."\n"); }
};
try {
    mkdir($app.'/public_html', 0700, true);
    mkdir($persist.'/branding', 0700, true);
    mkdir($wrong, 0700, true);
    file_put_contents($persist.'/branding/logo.svg', 'logo test asset');
    symlink($persist, $app.'/public_html/uploads');
    $good = PublicUploadsSafety::inspect($app, $persist);
    $assert($good['ok'] === true, 'external branding symlink accepted');
    $assert($good['brand_file_count'] === 1, 'branding preserved');
    $assert($good['production_deployment_approved'] === false, 'does not approve production');
    $assert(PublicUploadsSafety::inspect($app, $wrong)['ok'] === false, 'wrong uploads root rejected');
    unlink($app.'/public_html/uploads');
    mkdir($app.'/public_html/uploads', 0700);
    $assert(PublicUploadsSafety::inspect($app, $app.'/public_html/uploads')['ok'] === false,
        'local uploads directory rejected');
    $assert(PublicUploadsSafety::inspect($app, $persist)['ok'] === false,
        'external expectation rejects local uploads');
    rmdir($app.'/public_html/uploads');
    symlink($persist, $app.'/public_html/uploads');
    symlink($wrong, $persist.'/branding/unexpected-link');
    $assert(PublicUploadsSafety::inspect($app, $persist)['ok'] === false, 'branding symlink requires review');
    unlink($persist.'/branding/unexpected-link');
    $assert(PublicUploadsSafety::inspect($app, $persist)['ok'] === true, 'restored branded fixture accepted');
} catch (Throwable $e) {
    $fails++; fwrite(STDERR, "FAIL uploads preflight: fixture (".get_class($e).")\n");
} finally {
    @unlink($app.'/public_html/uploads');
    @unlink($persist.'/branding/unexpected-link');
    @unlink($persist.'/branding/logo.svg');
    @rmdir($app.'/public_html/uploads');
    @rmdir($persist.'/branding');
    @rmdir($persist);
    @rmdir($root.'/persist');
    @rmdir($wrong);
    @rmdir($app.'/public_html');
    @rmdir($app);
    @rmdir($root);
}
if ($fails) exit(1);
echo "PASS external branding persistence ($cases assertions), no production action\n";
