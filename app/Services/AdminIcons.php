<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

/**
 * Fixed, locally rendered SVG icon catalogue for the publisher interface.
 * No icon fonts, CDN calls, emojis or runtime script are required.
 */
final class AdminIcons
{
    private const PATHS = [
        'layout-dashboard'=>'<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'newspaper'=>'<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h6v5H7zM16 8h2M16 11h2M7 16h11"/>',
        'scan-text'=>'<path d="M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4M8 9h8M8 12h8M8 15h5"/>',
        'library-big'=>'<rect x="3" y="4" width="5" height="17" rx="1"/><rect x="10" y="4" width="5" height="17" rx="1"/><path d="m17 5 4 15M3 9h5M10 16h5"/>',
        'file-text'=>'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h8"/>',
        'bookmark'=>'<path d="M6 3h12v18l-6-4-6 4V3z"/>',
        'bot'=>'<rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 8V4M10 4h4M8 14h.01M16 14h.01M9 17h6"/>',
        'compass'=>'<circle cx="12" cy="12" r="9"/><path d="m15.7 8.3-2.2 5.2-5.2 2.2 2.2-5.2z"/>',
        'book-open'=>'<path d="M12 7v14M3 5a9 9 0 0 1 9 2 9 9 0 0 1 9-2v14a9 9 0 0 0-9 2 9 9 0 0 0-9-2z"/>',
        'tags'=>'<path d="M3 4v7l9 9 8-8-9-9H3zM7.5 7.5h.01M9 2h4l9 9-4 4"/>',
        'user-round'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M14 3a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/>',
        'credit-card'=>'<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'settings'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a2 2 0 0 0 .4 2l-2.8 2.8a2 2 0 0 0-2-.4 2 2 0 0 0-1.5 1.6h-3a2 2 0 0 0-1.5-1.6 2 2 0 0 0-2 .4L4.2 17a2 2 0 0 0 .4-2A2 2 0 0 0 3 13.5v-3A2 2 0 0 0 4.6 9a2 2 0 0 0-.4-2L7 4.2a2 2 0 0 0 2 .4A2 2 0 0 0 10.5 3h3A2 2 0 0 0 15 4.6a2 2 0 0 0 2-.4L19.8 7a2 2 0 0 0-.4 2 2 2 0 0 0 1.6 1.5v3A2 2 0 0 0 19.4 15z"/>',
        'shield-check'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4"/>',
        'sliders-horizontal'=>'<path d="M3 6h9M16 6h5M3 12h3M10 12h11M3 18h12M19 18h2"/><circle cx="14" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
        'workflow'=>'<rect x="2" y="3" width="7" height="6" rx="1"/><rect x="15" y="15" width="7" height="6" rx="1"/><path d="M5.5 9v6a3 3 0 0 0 3 3H15M15 6h3v9M15 6l2-2 2 2"/>',
        'clipboard-check'=>'<rect x="5" y="5" width="14" height="17" rx="2"/><path d="M9 5V3h6v2M9 14l2 2 4-4"/>',
        'activity'=>'<path d="M2 12h4l3-8 5 16 3-8h5"/>',
        'alert-triangle'=>'<path d="M10.3 3.9 2.1 18a2 2 0 0 0 1.7 3h16.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/>',
        'gauge'=>'<path d="M3 17a9 9 0 1 1 18 0M12 17l4-6M5 19h14"/><circle cx="12" cy="17" r="1"/>',
        'brain'=>'<path d="M12 18V5a3 3 0 0 0-5.7-1.4A4 4 0 0 0 4 10a4 4 0 0 0 1 7 3 3 0 0 0 7 1zM12 18V5a3 3 0 0 1 5.7-1.4A4 4 0 0 1 20 10a4 4 0 0 1-1 7 3 3 0 0 1-7 1z"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'file-check'=>'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 16l2 2 5-5"/>',
        'hard-drive'=>'<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 13h20M6 16h.01M10 16h.01"/>',
        'cpu'=>'<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 9h6v6H9zM9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>',
        'refresh-cw'=>'<path d="M20 11a8 8 0 0 0-14-5L4 8M4 4v4h4M4 13a8 8 0 0 0 14 5l2-2M16 16h4v4"/>',
        'search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'layers'=>'<path d="m12 2 9 5-9 5-9-5 9-5zM3 12l9 5 9-5M3 17l9 5 9-5"/>',
        'chevron-right'=>'<path d="m9 18 6-6-6-6"/>',
    ];

    public static function supports(string $icon): bool
    {
        return isset(self::PATHS[$icon]);
    }

    public static function svg(string $icon): string
    {
        // Fail safe to a known SVG rather than rendering generic circle placeholders.
        $path = self::PATHS[$icon] ?? self::PATHS['layers'];
        return '<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"'
            .' fill="none" stroke="currentColor" stroke-width="1.8"'
            .' stroke-linecap="round" stroke-linejoin="round">'.$path.'</svg>';
    }
}
