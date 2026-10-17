<?php
declare(strict_types=1);
namespace EpaperSaas\Core;

/**
 * Build and verify a slim application-root ZIP from the Git index.
 * Runtime storage and public uploads are deliberately excluded.
 * This class never reads or changes a production system.
 */
final class SlimReleaseZip
{
    private const MANIFEST = '__epdi/release-manifest.json';
    private const REQUIRED = [
        'app/bootstrap.php',
        'public_html/index.php',
        'bin/scheduler.php',
        'composer.json',
        'composer.lock',
        'vendor/autoload.php',
        'VERSION',
    ];

    private static function included(string $path): bool
    {
        if (str_starts_with($path, 'storage/')
            || str_starts_with($path, 'public_html/uploads/')) return false;
        if (preg_match('#(?:^|/)(?:\.github|\.git)(?:/|$)#', $path)) return false;
        if (preg_match('/\.(?:bak(?:-.*)?|old|orig|log)$/i', $path)) return false;
        if (preg_match('#^(?:app|bin|config|database|public_html|tests|vendor)/#D', $path)) return true;
        return in_array($path, [
            'VERSION', 'composer.json', 'composer.lock', 'tests-cumulative-check.php',
            'manifest.json', 'CRON.txt', 'SUPERVISOR.conf.example',
            'DIRECT-RECOVERY-SUPERVISOR.conf.example',
        ], true);
    }

    private static function safeName(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\')
            || str_contains($path, "\0") || str_contains($path, '//')
            || $path === self::MANIFEST) return false;
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') return false;
        }
        return true;
    }

    private static function protect(string $path): void
    {
        if (preg_match('#^(?:config/(?:config\.php|runtime\.php|install\.token)|storage/installed\.lock)$#iD', $path)
            || preg_match('#(?:^|/)(?:\.env(?:\.[^/]*)?|[^/]+\.(?:pem|p12|pfx|key)|service-account[^/]*\.json)$#iD', $path)
            || preg_match('#^public_html/apply-[^/]*hotfix\.php$#iD', $path)) {
            throw new \RuntimeException('Confidential or privileged file detected in release selection');
        }
    }

    private static function gitPaths(string $repository): array
    {
        $proc = @proc_open(
            ['git', '-C', $repository, 'ls-files', '-z', '--', 'codebase/'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes
        );
        if (!is_resource($proc)) throw new \RuntimeException('Git index cannot be read');
        fclose($pipes[0]);
        $raw = stream_get_contents($pipes[1]); fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($proc);
        if ($status !== 0 || !is_string($raw)) throw new \RuntimeException('Git index enumeration failed');
        $paths = array_filter(explode("\0", $raw), static fn($p) => $p !== '');
        sort($paths, SORT_STRING);
        return $paths;
    }

    public static function build(string $repository, string $output): array
    {
        if (!class_exists(\ZipArchive::class)) throw new \RuntimeException('PHP ZIP extension required');
        $repository = realpath($repository);
        $parent = realpath(dirname($output));
        if (!$repository || !$parent || !str_starts_with($output, '/')
            || is_file($output) || is_link($output)
            || $parent === $repository || str_starts_with($parent, $repository.'/')) {
            throw new \RuntimeException('Output must be a new ZIP outside the source repository');
        }
        $entries = [];
        foreach (self::gitPaths($repository) as $tracked) {
            if (!str_starts_with($tracked, 'codebase/')) continue;
            $name = substr($tracked, strlen('codebase/'));
            if (!self::safeName($name)) throw new \RuntimeException('Unsafe Git path');
            if (!self::included($name)) continue;
            self::protect($name);
            $file = $repository.'/'.$tracked;
            if (!is_file($file) || is_link($file) || !is_readable($file)) {
                throw new \RuntimeException('Release file missing, unreadable or symlinked');
            }
            $size = filesize($file);
            $hash = hash_file('sha256', $file);
            if (!is_int($size) || !is_string($hash)) throw new \RuntimeException('Release file hash unavailable');
            $entries[] = ['path' => $name, 'bytes' => $size, 'sha256' => $hash];
        }
        $names = array_column($entries, 'path');
        foreach (self::REQUIRED as $path) {
            if (!in_array($path, $names, true)) throw new \RuntimeException('Missing required runtime file');
        }
        $manifest = [
            'schema' => 'epdi-slim-runtime-zip-v1',
            'runtime_data_excluded' => true,
            'public_uploads_excluded' => true,
            'production_deployment_approved' => false,
            'files' => $entries,
        ];
        $zip = new \ZipArchive();
        if ($zip->open($output, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            throw new \RuntimeException('Could not create ZIP');
        }
        try {
            foreach ($entries as $entry) {
                if (!$zip->addFile($repository.'/codebase/'.$entry['path'], $entry['path'])) {
                    throw new \RuntimeException('Could not add release file');
                }
            }
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (!$zip->addFromString(self::MANIFEST, $json."\n")) {
                throw new \RuntimeException('Could not add release manifest');
            }
            if (!$zip->close()) throw new \RuntimeException('Could not finalize ZIP');
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($output);
            throw $e;
        }
        return self::verify($output);
    }

    public static function verify(string $archive): array
    {
        if (!class_exists(\ZipArchive::class)) throw new \RuntimeException('PHP ZIP extension required');
        if (!is_file($archive) || is_link($archive)) throw new \RuntimeException('Archive unavailable');
        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::RDONLY) !== true) throw new \RuntimeException('Invalid archive');
        try {
            $json = $zip->getFromName(self::MANIFEST);
            if ($json === false) throw new \RuntimeException('Release manifest missing');
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (($manifest['schema'] ?? '') !== 'epdi-slim-runtime-zip-v1'
                || ($manifest['runtime_data_excluded'] ?? null) !== true
                || ($manifest['public_uploads_excluded'] ?? null) !== true
                || ($manifest['production_deployment_approved'] ?? null) !== false
                || !isset($manifest['files']) || !is_array($manifest['files'])) {
                throw new \RuntimeException('Untrusted manifest schema or assertions');
            }
            $expected = [];
            foreach ($manifest['files'] as $entry) {
                $path = $entry['path'] ?? null;
                if (!is_string($path) || !self::safeName($path) || !self::included($path)
                    || !is_int($entry['bytes'] ?? null)
                    || ($entry['bytes'] ?? -1) < 0
                    || !is_string($entry['sha256'] ?? null)
                    || preg_match('/^[0-9a-f]{64}$/D', $entry['sha256']) !== 1
                    || isset($expected[$path])) throw new \RuntimeException('Invalid manifest entry');
                self::protect($path);
                $expected[$path] = $entry;
            }
            foreach (self::REQUIRED as $name) {
                if (!isset($expected[$name])) throw new \RuntimeException('Required entry absent');
            }
            $seen = [];
            $seenFiles = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!is_string($name) || isset($seen[$name])) {
                    throw new \RuntimeException('Unsafe or duplicate ZIP entry');
                }
                $isDir = str_ends_with($name, '/');
                $canonical = $isDir ? substr($name, 0, -1) : $name;
                if (($name !== self::MANIFEST && !self::safeName($canonical))
                    || ($isDir && $canonical === self::MANIFEST)) {
                    throw new \RuntimeException('Unsafe or duplicate ZIP entry');
                }
                $seen[$name] = true;
                $opsys = 0; $attr = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attr)
                    && ((($attr >> 16) & 0170000) === 0120000)) {
                    throw new \RuntimeException('Archive contains symlink');
                }
                if ($isDir) {
                    $allowedDirectory = $canonical === '__epdi'
                        || str_starts_with(self::MANIFEST, $canonical.'/');
                    if (!$allowedDirectory) {
                        foreach (array_keys($expected) as $expectedName) {
                            if (str_starts_with($expectedName, $canonical.'/')) {
                                $allowedDirectory = true;
                                break;
                            }
                        }
                    }
                    $stat = $zip->statIndex($i);
                    if (!$allowedDirectory || !is_array($stat) || ($stat['size'] ?? -1) !== 0) {
                        throw new \RuntimeException('Unexpected ZIP entry');
                    }
                    continue;
                }
                $seenFiles[$name] = true;
                if ($name === self::MANIFEST) continue;
                if (!isset($expected[$name])) throw new \RuntimeException('Unexpected ZIP entry');
                $data = $zip->getFromIndex($i);
                if (!is_string($data) || strlen($data) !== $expected[$name]['bytes']
                    || !hash_equals($expected[$name]['sha256'], hash('sha256', $data))) {
                    throw new \RuntimeException('Release entry integrity mismatch');
                }
            }
            if (count($seenFiles) !== count($expected) + 1) {
                throw new \RuntimeException('Missing or extra ZIP entries');
            }
            return [
                'ok' => true,
                'schema' => $manifest['schema'],
                'application_files' => count($expected),
                'application_bytes' => array_sum(array_column($manifest['files'], 'bytes')),
                'archive_sha256' => hash_file('sha256', $archive),
                'production_deployment_approved' => false,
                'cloudways_deployment_rehearsed' => false,
            ];
        } finally {
            $zip->close();
        }
    }
}
