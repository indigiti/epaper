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
    $safeReasons = [
        "PHP ZIP extension required", "Output must be a new ZIP outside the source repository",
        "Git index cannot be read", "Git index enumeration failed", "Unsafe Git path",
        "Confidential or privileged file detected in release selection",
        "Release file missing, unreadable or symlinked", "Release file hash unavailable",
        "Missing required runtime file", "Could not create ZIP", "Could not add release file",
        "Could not add release manifest", "Could not finalize ZIP", "Archive unavailable",
        "Invalid archive", "Release manifest missing", "Untrusted manifest schema or assertions",
        "Invalid manifest entry", "Required entry absent", "Unsafe or duplicate ZIP entry",
        "Unexpected ZIP entry", "Archive contains symlink", "Release entry integrity mismatch",
        "Missing or extra ZIP entries",
    ];
    $reason = in_array($e->getMessage(), $safeReasons, true) ? $e->getMessage() : "internal packaging error";
    fwrite(STDERR, "Slim ZIP build/verification failed: ".$reason.".\n");
    exit(1);
}
