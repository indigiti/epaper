<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

/** Keep display branding separate from the verified Platform tenant identity. */
final class BrandIdentity
{
    public static function websiteName(array $settings, string $verifiedPublisher): string
    {
        $website = self::clean($settings['brand.site_name'] ?? '');
        if ($website !== '' && !self::generic($website)) return $website;
        $publisher = self::clean($verifiedPublisher);
        return $publisher !== '' && !self::generic($publisher) ? $publisher : 'ePaper';
    }

    public static function generic(string $name): bool
    {
        return $name === '' || strcasecmp(trim($name), 'ePaper SaaS') === 0;
    }

    private static function clean(mixed $value): string
    {
        $name = trim(strip_tags((string)$value));
        return (string)(preg_replace('/[\x00-\x1f\x7f]/u', ' ', $name) ?? '');
    }
}
