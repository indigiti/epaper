<?php
declare(strict_types=1);
/**
 * Local-only simulation of immutable code releases with external storage.
 * Does NOT execute or certify Cloudways/DigiOps, git pull, rsync, live DB,
 * production storage, the live deployer's exclusion rules or worker paths.
 */
require_once dirname(__DIR__).'/app/Core/RestoreInventory.php';
require_once dirname(__DIR__).'/app/Core/StorageDeploymentSafety.php';

use EpaperSaas\Core\RestoreInventory;
use EpaperSaas\Core\StorageDeploymentSafety;

if (PHP_SAPI !== 'cli') exit(2);
$root = sys_get_temp_dir().'/epdi-release-fixture-'.bin2hex(random_bytes(8));
$cases = 0; $fails = 0;
$assert = static function (bool $ok, string $label) use (&$cases, &$fails): void {
    $cases++;
    if (!$ok) { $fails++; fwrite(STDERR, "FAIL release activation: {$label}\n"); }
};
$mkdir = static function (string $path): void {
    if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Cannot initialize disposable fixture');
    }
};
$remove = static function (string $dir): void {
    if (!is_dir($dir) || is_link($dir)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isLink() || $entry->isFile()) unlink($entry->getPathname());
        elseif ($entry->isDir()) rmdir($entry->getPathname());
    }
    rmdir($dir);
};
try {
    $legacy = $root.'/legacy';
    $persistent = $root.'/persistent/storage';
    $releases = $root.'/releases';
    $fixtures = [
        'media/page.webp' => 'example page pixels',
        'uploads/source.pdf' => '%PDF-1.4 fixture',
        'clips/reader-clip.webp' => 'example reader clipping',
    ];
    foreach (['media', 'uploads', 'clips', 'cache', 'logs'] as $category) {
        $mkdir($legacy.'/storage/'.$category);
        $mkdir($persistent.'/'.$category);
    }
    foreach ($fixtures as $relative => $data) {
        file_put_contents($legacy.'/storage/'.$relative, $data);
    }
    $baseline = RestoreInventory::snapshot($legacy.'/storage');
    foreach (array_keys($fixtures) as $relative) {
        if (!copy($legacy.'/storage/'.$relative, $persistent.'/'.$relative)) {
            throw new RuntimeException('Disposable fixture copy failed');
        }
    }
    $assert(RestoreInventory::compare($baseline, RestoreInventory::snapshot($persistent))['match'] === true,
        'legacy backup copied to independent storage');

    foreach (['v1', 'v2'] as $release) {
        $app = $releases.'/'.$release;
        $mkdir($app.'/app');
        $mkdir($app.'/public_html');
        file_put_contents($app.'/app/bootstrap.php', '<?php // disposable fixture');
        file_put_contents($app.'/public_html/index.php', '<?php // disposable fixture');
        if (!symlink($persistent, $app.'/storage')) throw new RuntimeException('Symlink fixture failed');
        $check = StorageDeploymentSafety::inspect($app, $persistent, $baseline);
        $assert($check['ok'] === true, 'external storage passes in '.$release);
        $assert($check['production_deployment_approved'] === false,
            'fixture never approves production deployment in '.$release);
    }

    $current = $root.'/current';
    if (!symlink($releases.'/v1', $current)) throw new RuntimeException('Activation fixture failed');
    $assert(realpath($current) === realpath($releases.'/v1'), 'initial release active');
    $assert(realpath($current.'/storage') === realpath($persistent), 'active release uses external storage');

    if (!symlink($releases.'/v2', $root.'/pending')) throw new RuntimeException('Pending fixture failed');
    if (!rename($root.'/pending', $current)) throw new RuntimeException('Link switch failed');
    $assert(realpath($current) === realpath($releases.'/v2'), 'staged release activated');
    $assert(RestoreInventory::compare($baseline, RestoreInventory::snapshot($current.'/storage'))['match'] === true,
        'backup inventory retained after release switch');

    if (!symlink($releases.'/v1', $root.'/pending')) throw new RuntimeException('Rollback fixture failed');
    if (!rename($root.'/pending', $current)) throw new RuntimeException('Rollback switch failed');
    $assert(realpath($current) === realpath($releases.'/v1'), 'previous release activated for rollback');
    $assert(RestoreInventory::compare($baseline, RestoreInventory::snapshot($current.'/storage'))['match'] === true,
        'inventory retained after rollback');

    $remove($releases.'/v2');
    $assert(RestoreInventory::compare($baseline, RestoreInventory::snapshot($persistent))['match'] === true,
        'deleting inactive code checkout preserves persistent content');

    $unsafe = $root.'/unsafe';
    $mkdir($unsafe.'/app');
    $mkdir($unsafe.'/public_html');
    file_put_contents($unsafe.'/app/bootstrap.php', '<?php');
    file_put_contents($unsafe.'/public_html/index.php', '<?php');
    $mkdir($unsafe.'/storage/media');
    $check = StorageDeploymentSafety::inspect($unsafe, $persistent, $baseline);
    $assert($check['ok'] === false, 'in-checkout storage rejected');
    $assert($check['storage_link_matches_persistent'] === false, 'in-checkout storage not certified');
} catch (Throwable $e) {
    $fails++;
    fwrite(STDERR, "FAIL release activation: disposable fixture incomplete (".get_class($e).")\n");
} finally {
    $remove($root);
}
if ($fails !== 0) exit(1);
echo "PASS disposable external-storage release activation ({$cases} assertions); actual deploy driver NOT verified\n";
