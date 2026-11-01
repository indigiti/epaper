<?php
namespace Digiti\Core;

final class Config
{
    private static array $data = [];

    public static function load(string $root): void
    {
        $privateDir = trim((string)(getenv('DIGITI_PRIVATE_CONFIG_DIR') ?: ''));
        if ($privateDir !== '') {
            $candidate = realpath($privateDir);
            $publicRoot = realpath($root.'/public_html');
            if (!$candidate || !is_dir($candidate) || ($publicRoot && ($candidate === $publicRoot || str_starts_with($candidate, $publicRoot.DIRECTORY_SEPARATOR)))) {
                throw new \RuntimeException('Platform private configuration directory is invalid or public');
            }
            $file = $candidate.'/config.php';
        } else {
            $file = $root.'/config/config.php';
        }
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException('Platform private runtime configuration is missing');
        }
        $data = require $file;
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid configuration file.');
        }
        self::$data = $data;
        $tz = self::get('app.timezone', 'UTC');
        date_default_timezone_set((string)$tz);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$data;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) return $default;
            $value = $value[$segment];
        }
        return $value;
    }

    public static function all(): array { return self::$data; }
}
