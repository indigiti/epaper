<?php
namespace EpaperSaas\Core;

final class Build
{
    private static ?string $version = null;

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
        return $path . $separator . 'v=' . rawurlencode(self::version());
    }
}
