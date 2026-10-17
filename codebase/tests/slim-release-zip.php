<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/SlimReleaseZip.php';
use EpaperSaas\Core\SlimReleaseZip;

if (PHP_SAPI !== 'cli' || !class_exists(ZipArchive::class)) exit(2);
$dir = sys_get_temp_dir().'/epdi-slim-zip-test-'.bin2hex(random_bytes(6));
if (!mkdir($dir, 0700)) exit(2);
$zipPath = $dir.'/release.zip';
$cases = 0; $failures = 0;
$assert = static function (bool $condition, string $label) use (&$cases, &$failures): void {
    $cases++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL slim artifact: {$label}\n");
    }
};
$reject = static function (callable $fn): bool {
    try { $fn(); return false; }
    catch (Throwable) { return true; }
};
try {
    $result = SlimReleaseZip::build(dirname(__DIR__, 2), $zipPath);
    $assert($result['ok'] === true, 'zip created and verified');
    $assert($result['application_files'] > 100, 'has application source');
    $assert($result['production_deployment_approved'] === false, 'never authorizes promotion');
    $assert($result['cloudways_deployment_rehearsed'] === false, 'no simulated Cloudways attestation');
    $assert(($result['digiops_direct_deployment_compatible'] ?? null) === false,
        'staging ZIP must never claim direct DigiOps compatibility');
    $assert(($result['artifact_layout'] ?? '') === 'cloudways-php-application-root',
        'package layout identified explicitly');
    $assert(hash_file('sha256', $zipPath) === $result['archive_sha256'], 'archive SHA-256 matches');
    $again = SlimReleaseZip::verify($zipPath);
    $assert($again === $result, 'independent verification matches');
    $z = new ZipArchive();
    if ($z->open($zipPath, ZipArchive::RDONLY) !== true) throw new RuntimeException('Could not open fixture');
    $names = [];
    for ($i = 0; $i < $z->numFiles; $i++) $names[] = $z->getNameIndex($i);
    $assert(in_array('app/bootstrap.php', $names, true), 'runtime bootstrap included');
    $assert(in_array('public_html/index.php', $names, true), 'web entry included');
    $assert(!in_array('index.php', $names, true)
        && !in_array('public/index.php', $names, true)
        && !in_array('dist/index.html', $names, true),
        'reject treating Cloudways app-root ZIP as a DigiOps public release');
    $assert(!in_array('storage/media/.keep', $names, true), 'no runtime skeleton');
    $assert(count(array_filter($names, static fn($n) => str_starts_with($n, 'storage/'))) === 0,
        'no storage files in deployable package');
    $assert(count(array_filter($names, static fn($n) => str_starts_with($n, 'public_html/uploads/'))) === 0,
        'branding and other customer uploads not overwritten');
    $assert(!in_array('config/config.php', $names, true), 'live credentials never included');
    $assert(!in_array('SHA256SUMS', $names, true), 'historical checksums not presented as new release');
    $z->close();

    $assert($reject(static fn() => SlimReleaseZip::build(dirname(__DIR__, 2),
        dirname(__DIR__).'/reject-build.zip')), 'output cannot be written into checkout');

    $z = new ZipArchive();
    if ($z->open($zipPath) !== true) throw new RuntimeException('Could not modify fixture');
    $z->addFromString('app/bootstrap.php', 'tampered runtime code');
    $z->close();
    $assert($reject(static fn() => SlimReleaseZip::verify($zipPath)), 'tampered code rejected');
    unlink($zipPath);

    SlimReleaseZip::build(dirname(__DIR__, 2), $zipPath);
    $z = new ZipArchive();
    if ($z->open($zipPath) !== true) throw new RuntimeException('Could not modify fixture');
    $z->addFromString('storage/media/removed-on-deploy.webp', 'private page');
    $z->close();
    $assert($reject(static fn() => SlimReleaseZip::verify($zipPath)), 'inserted customer media rejected');
} catch (Throwable $e) {
    $failures++;
    fwrite(STDERR, "FAIL slim artifact: fixture error (".get_class($e).")\n");
} finally {
    if (is_file($zipPath)) unlink($zipPath);
    rmdir($dir);
}
if ($failures) exit(1);
echo "PASS slim Cloudways ZIP package ($cases assertions); no production deployment approved\n";
