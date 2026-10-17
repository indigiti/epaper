<?php
declare(strict_types=1);
namespace EpaperSaas\Core;

/**
 * Read-only path gate for public branding uploads in a slim code release.
 * This cannot certify that the external directory is backed up or immutable.
 */
final class PublicUploadsSafety
{
    public static function inspect(string $releaseRoot, string $persistentUploads): array
    {
        $result = [
            'ok' => false,
            'app_valid' => false,
            'persistent_uploads_external' => false,
            'public_uploads_link_matches_persistent' => false,
            'brand_file_count' => 0,
            'production_deployment_approved' => false,
        ];
        $app = realpath($releaseRoot);
        $public = $app === false ? false : realpath($app.'/public_html');
        $uploads = realpath($persistentUploads);
        if ($app === false || $public === false || !is_dir($public)) return $result;
        $result['app_valid'] = true;
        if ($uploads === false || !is_dir($uploads)) return $result;
        if ($uploads === $app || str_starts_with($uploads, rtrim($app, '/').'/')
            || $uploads === $public || str_starts_with($uploads, rtrim($public, '/').'/')) {
            return $result;
        }
        $result['persistent_uploads_external'] = true;
        $link = $app.'/public_html/uploads';
        if (!is_link($link) || realpath($link) !== $uploads) return $result;
        $result['public_uploads_link_matches_persistent'] = true;
        $brandDir = $uploads.'/branding';
        if (is_dir($brandDir)) {
            try {
                $i = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($brandDir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($i as $entry) {
                    if ($entry->isLink()) return $result; // manual review required
                    if ($entry->isFile()) $result['brand_file_count']++;
                }
            } catch (\Throwable) {
                return $result;
            }
        }
        $result['ok'] = true;
        return $result;
    }
}
