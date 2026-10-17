<?php
declare(strict_types=1);
/**
 * Read-only inspection of an already populated, ISOLATED STAGING release.
 * Do not point at production. Does not mutate symlinks, copy files or deploy.
 *
 * php bin/slim-release-target-preflight.php \
 *  --app-root=/staging/releases/vNext \
 *  --persistent-storage=/staging/shared/storage \
 *  --persistent-public-uploads=/staging/shared/public-uploads \
 *  --backup-inventory=/private/backup-inventory.json
 */
if (PHP_SAPI !== 'cli') exit(2);
require_once dirname(__DIR__).'/app/Core/RestoreInventory.php';
require_once dirname(__DIR__).'/app/Core/StorageDeploymentSafety.php';
require_once dirname(__DIR__).'/app/Core/PublicUploadsSafety.php';
use EpaperSaas\Core\StorageDeploymentSafety;
use EpaperSaas\Core\PublicUploadsSafety;
$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (!preg_match('/^--(app-root|persistent-storage|persistent-public-uploads|backup-inventory)=(\/.+)$/D', $argument, $match)) {
        fwrite(STDERR, "Four absolute arguments required; inspect script usage header.\n");
        exit(2);
    }
    $options[$match[1]] = $match[2];
}
foreach (['app-root','persistent-storage','persistent-public-uploads','backup-inventory'] as $required) {
    if (!isset($options[$required])) {
        fwrite(STDERR, "Missing required staging argument.\n");
        exit(2);
    }
}
try {
    $inventoryPath = realpath($options['backup-inventory']);
    if (!$inventoryPath || !is_file($inventoryPath) || !is_readable($inventoryPath)) {
        throw new RuntimeException('Missing private inventory');
    }
    $inventory = json_decode((string)file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($inventory)) throw new RuntimeException('Malformed inventory');
    $storage = StorageDeploymentSafety::inspect(
        $options['app-root'], $options['persistent-storage'], $inventory
    );
    $uploads = PublicUploadsSafety::inspect(
        $options['app-root'], $options['persistent-public-uploads']
    );
    $ok = $storage['ok'] === true && $uploads['ok'] === true;
    echo json_encode([
        'staging_source_path_gate_passed' => $ok,
        'private_storage' => $storage,
        'public_uploads' => $uploads,
        'actual_deployment_driver_verified' => false,
        'operator_backup_verified' => false,
        'production_credentials_rotated_verified' => false,
        'production_deployment_approved' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit($ok ? 0 : 1);
} catch (Throwable) {
    fwrite(STDERR, "Staging target preflight could not complete; examine private configuration.\n");
    exit(2);
}
