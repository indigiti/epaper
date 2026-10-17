<?php
namespace Digiti\Core;

final class Config
{
    private static array $data = [];

    public static function load(string $root): void
    {
        $file = $root . '/config/config.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Platform is not configured. Run /install.php.');
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
