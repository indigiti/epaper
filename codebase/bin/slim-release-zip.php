<?php
declare(strict_types=1);
/**
 * Source-only release packaging; never extract on production.
 * php bin/slim-release-zip.php --output=/private/build/epdi-slim.zip
 * php bin/slim-release-zip.php --verify=/private/build/epdi-slim.zip
 */
if (PHP_SAPI !== 'cli') exit(2);
require_once dirname(__DIR__).'/app/Core/SlimReleaseZip.php';
use EpaperSaas\Core\SlimReleaseZip;

if (count($argv) !== 2 || !preg_match('#^--(output|verify)=(/[^\\x00\\r\\n]+)$#D', $argv[1], $match)) {
    fwrite(STDERR, "Usage: php bin/slim-release-zip.php --output=/absolute/new.zip OR --verify=/absolute/existing.zip\n");
    exit(2);
}
try {
    $result = $match[1] === 'output'
        ? SlimReleaseZip::build(dirname(__DIR__, 2), $match[2])
        : SlimReleaseZip::verify($match[2]);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $e) {
    // Do not disclose private file locations or credential-containing ZIP members.
    fwrite(STDERR, "Slim ZIP build/verification failed; inspect private build diagnostics.\n");
    exit(1);
}
