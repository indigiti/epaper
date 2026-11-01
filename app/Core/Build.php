<?php
namespace EpaperSaas\Core;

/**
 * Lightweight release and per-asset revision tokens.
 *
 * The site serves /assets/ with max-age=31536000, immutable. A constant
 * VERSION-only URL therefore leaves changed files permanently cached in the
 * browser, CDN and service-worker cache. Metadata-derived tokens make changed
 * asset URLs unique without hashing JS/CSS bytes on every PHP request.
 */
final class Build
{
    private static ?string $version = null;
    /** @var array<string,string> */
    private static array $assetRevisions = [];

    public static function version(): string
    {
        if (self::$version !== null) return self::$version;
        $root = dirname(__DIR__, 2);
        $value = trim((string) @file_get_contents($root . '/VERSION'));
        self::$version = $value !== '' ? $value : 'unknown';
        return self::$version;
    }

    public static function asset(string $path): string
    {
        $separator = str_contains($path, '?') ? '&' : '?';
        return $path . $separator . 'v=' . rawurlencode(self::assetVersion($path));
    }

    /**
     * Only application-owned static /assets/ files are stat'ed. Version tokens
     * stay stable across requests until the underlying file changes.
     * Missing assets or arbitrary paths keep their normal release token.
     */
    private static function assetVersion(string $path): string
    {
        $release = self::version();
        $assetPath = parse_url($path, PHP_URL_PATH);
        if (!is_string($assetPath) ||
            !preg_match('~^/assets/[a-zA-Z0-9._/-]+$~D', $assetPath) ||
            str_contains($assetPath, '..')) {
            return $release;
        }
        if (isset(self::$assetRevisions[$assetPath])) {
            return $release . '.' . self::$assetRevisions[$assetPath];
        }
        $root = dirname(__DIR__, 2) . '/public_html';
        $stat = @stat($root . $assetPath);
        if (!is_array($stat) || !isset($stat['mtime'], $stat['size']) ||
            ($stat['mode'] & 0170000) !== 0100000) {
            return $release;
        }
        // filemtime + bytes is cheap to obtain and changes on Git checkout.
        // All deployment-boundary releases also rotate the top-level VERSION.
        $stamp = base_convert((string)(int)$stat['mtime'], 10, 36)
            . '-' . base_convert((string)(int)$stat['size'], 10, 36);
        self::$assetRevisions[$assetPath] = $stamp;
        return $release . '.' . $stamp;
    }
}
