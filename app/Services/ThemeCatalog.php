<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

use EpaperSaas\Core\Auth;
use EpaperSaas\Core\Http;

/**
 * Separate presentation choices for each application surface.
 * The labels are the persisted tenant values; keep them stable for existing publishers.
 */
final class ThemeCatalog
{
    private const THEMES = [
        'frontend' => ['Default', 'Newsstand Modern', 'Editorial Portal', 'Classic Newspaper', 'Digital Magazine'],
        'reader' => ['Newsstand Replica', 'Editorial Luxe', 'Metro Pulse', 'Heritage Press', 'Apple Editorial', 'Default'],
        'admin' => ['Publisher Pro', 'Executive Slate', 'Midnight Desk', 'Default'],
        'auth' => ['Split Editorial', 'Gradient Focus', 'Minimal Access', 'Default'],
    ];

    private static bool $previewActive = false;

    public static function options(string $surface): array
    {
        return self::THEMES[$surface] ?? [];
    }

    public static function default(string $surface): string
    {
        return self::THEMES[$surface][0] ?? 'Default';
    }

    public static function accepts(string $surface, string $theme): bool
    {
        return in_array($theme, self::options($surface), true);
    }

    /**
     * Preview is request-only and restricted to a current publisher administrator.
     * It never persists or affects anonymous pages / public shared-cache variants.
     */
    public static function resolve(string $surface, string $saved): string
    {
        self::$previewActive = false;
        $theme = self::accepts($surface, $saved) ? $saved : self::default($surface);
        if (!in_array($surface, ['frontend', 'reader', 'admin'], true)) {
            return $theme;
        }
        $requestedSurface = $_GET['theme_surface'] ?? null;
        $requestedTheme = $_GET['theme_preview'] ?? null;
        if (!is_string($requestedSurface) || $requestedSurface !== $surface
            || !is_string($requestedTheme) || !self::accepts($surface, $requestedTheme)) {
            return $theme;
        }
        if (!Auth::user()) {
            return $theme;
        }
        $membership = Auth::currentMembership();
        $role = strtoupper((string) ($membership['role_key'] ?? ''));
        if (!in_array($role, ['OWNER', 'ADMIN', 'PUBLISHER_ADMIN'], true)) {
            return $theme;
        }
        self::$previewActive = true;
        Http::privateNoStore();
        return $requestedTheme;
    }

    public static function previewActive(): bool
    {
        return self::$previewActive;
    }
}
