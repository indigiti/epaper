<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

use EpaperSaas\Core\Auth;
use EpaperSaas\Core\Build;
use EpaperSaas\Core\Http;

/**
 * Tenant-controlled, optional Vanta HALO enhancement.
 * No effect is ever rendered on Admin, Reader, Auth or account pages.
 */
final class PublicHalo
{
    public static function enabled(): bool
    {
        if((string)TenantData::setting('appearance.home_background_effect','Off')==='Halo'){
            return true;
        }
        if(($_GET['effect_preview']??null)!=='halo')return false;
        // Query preview must never be available to an anonymous visitor or reader.
        if(!Auth::user())return false;
        $membership=Auth::currentMembership();
        $role=strtoupper((string)($membership['role_key']??''));
        if(!in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN'],true))return false;
        Http::privateNoStore();
        return true;
    }

    /**
     * Animate only the supported homepage hero types. Print-style themes
     * (Editorial Portal and Classic Newspaper) preserve their typography.
     */
    public static function decorate(string $home,string $theme): string
    {
        if(!self::enabled())return $home;
        $marker=match($theme){
            'Default'=>'<section class="newsstand-hero">',
            'Newsstand Modern'=>'<section class="fth-hero fth-newsstand-hero">',
            'Digital Magazine'=>'<section class="fth-hero fth-magazine-hero">',
            default=>null,
        };
        if(!$marker||!str_contains($home,$marker))return $home;
        $layer='<div class="epdi-halo-layer" aria-hidden="true" data-epaper-halo'
            .' data-three-src="'.self::e(Build::asset('/assets/vendor/three.r134.min.js')).'"'
            .' data-halo-src="'.self::e(Build::asset('/assets/vendor/vanta.halo.0.5.24.min.js')).'"></div>';
        $replacement=str_replace('">',' has-vanta-halo">',$marker).$layer;
        return substr_replace($home,$replacement,strpos($home,$marker),strlen($marker));
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }
}
