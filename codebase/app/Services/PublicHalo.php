<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

use EpaperSaas\Core\Auth;
use EpaperSaas\Core\Build;
use EpaperSaas\Core\Http;

/**
 * Backwards-compatible entry point for publisher-controlled homepage effects.
 * Remains homepage-only and opt-in; all reader/admin/auth canvases are excluded.
 */
final class PublicHalo
{
    public static function selectedEffect(): string
    {
        $saved=(string)TenantData::setting('appearance.home_background_effect','Off');
        if(!in_array($saved,BackgroundEffects::EFFECTS,true))$saved='Off';
        $query=trim(strtolower((string)($_GET['effect_preview']??'')));
        $names=['off'=>'Off','halo'=>'Halo','waves'=>'Waves','net'=>'Net','dots'=>'Dots'];
        if($query===''||!isset($names[$query]))return $saved;

        // Preview must never be used to inject a new animation into a public
        // cache. Only active publisher managers can override saved selection.
        if(!Auth::user())return $saved;
        $role=strtoupper((string)(Auth::currentMembership()['role_key']??''));
        if(!in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN'],true))return $saved;
        Http::privateNoStore();
        return $names[$query];
    }

    public static function enabled(): bool
    {
        return self::selectedEffect()!=='Off';
    }

    public static function decorate(string $home,string $theme,?bool $enabled=null): string
    {
        $effect=$enabled===null?self::selectedEffect():($enabled?'Halo':'Off');
        if($effect==='Off'||!BackgroundEffects::asset($effect))return $home;
        $marker=match($theme){
            'Default'=>'<section class="newsstand-hero">',
            'Newsstand Modern'=>'<section class="fth-hero fth-newsstand-hero">',
            'Digital Magazine'=>'<section class="fth-hero fth-magazine-hero">',
            default=>null,
        };
        if(!$marker||!str_contains($home,$marker))return $home;

        // The cache and settings stack is tenant scoped. Invalid legacy values
        // cannot reach a shader; normalize all numeric and colour fields.
        try{
            $config=BackgroundEffects::browserOptions(
                $enabled===null?BackgroundEffects::saved():BackgroundEffects::defaults()
            );
        }catch(\InvalidArgumentException $e){
            $config=BackgroundEffects::browserOptions(BackgroundEffects::defaults());
        }
        $json=json_encode($config,JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        if(!is_string($json))return $home;
        $asset=BackgroundEffects::asset($effect);
        $layer='<div class="epdi-halo-layer" aria-hidden="true" data-epaper-halo'
            .' data-effect-name="'.self::e(strtoupper($effect)).'"'
            .' data-effect-config="'.self::e($json).'"'
            .' data-three-src="'.self::e(Build::asset('/assets/vendor/three.r134.min.js')).'"'
            .' data-halo-src="'.self::e(Build::asset((string)$asset)).'"></div>';
        $replacement=str_replace('">',' has-vanta-halo">',$marker).$layer;
        return substr_replace($home,$replacement,strpos($home,$marker),strlen($marker));
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }
}
