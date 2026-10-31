<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

/**
 * Zero-JS appearance previews: CSS-rendered miniature interface illustrations,
 * backed by the same theme catalogue and saved selections as the real shells.
 * Admin access must be enforced by the existing AdminRoutePolicy.
 */
final class AppearanceGallery
{
    private const SURFACES = [
        'frontend' => ['Frontend designs', 'Choose the public homepage structure. Your existing Default remains available.'],
        'admin' => ['Admin workspace', 'Change the complete publisher dashboard palette and visual hierarchy.'],
        'reader' => ['Reader appearance', 'Change reader chrome only. Page zoom, clipping and editions stay intact.'],
        'auth' => ['Authentication', 'Change the sign-in presentation without altering identity verification.'],
    ];

    public static function render(array $saved, ?string $readerPath): string
    {
        $html='<section class="panel appearance-studio" data-appearance-gallery aria-labelledby="appearance-studio-title">'
            .'<div class="appearance-studio-head"><div><span class="eyebrow">VISUAL DESIGN SYSTEM</span>'
            .'<h2 id="appearance-studio-title">Theme gallery</h2>'
            .'<p>Choose a design and use its preview before saving. These small samples are CSS illustrations, not screenshots of your live publisher data.</p></div>'
            .'<span class="appearance-studio-count">5 frontends · 4 admin themes · 6 reader themes · 4 login themes</span></div>';
        foreach(self::SURFACES as $surface=>[$heading,$description]){
            $opts=ThemeCatalog::options($surface);
            $active=(string)($saved[$surface]??ThemeCatalog::default($surface));
            $html.='<section class="appearance-studio-section" data-appearance-surface="'.self::e($surface).'">'
                .'<div class="appearance-studio-section-head"><h3>'.self::e($heading).'</h3><p>'.self::e($description).'</p></div>'
                .'<div class="appearance-studio-grid">';
            foreach($opts as $name){
                $slug=self::slug($name);
                $selected=$name===$active;
                $link=match($surface){
                    'frontend'=>'/?'.http_build_query(['theme_surface'=>'frontend','theme_preview'=>$name]),
                    'admin'=>'/admin/settings/appearance?'.http_build_query(['theme_surface'=>'admin','theme_preview'=>$name]),
                    'reader'=>$readerPath!==null?$readerPath.'?'.http_build_query(['theme_surface'=>'reader','theme_preview'=>$name]):null,
                    default=>null,
                };
                $html.='<article class="appearance-demo-card'.($selected?' is-selected':'').'"'
                    .' data-demo-theme="'.self::e($slug).'">'
                    .self::mockup($surface,$slug)
                    .'<div class="appearance-demo-footer"><div><strong>'.self::e($name).'</strong>'
                    .'<small>'.($selected?'Currently selected':'Available design').'</small></div>'
                    .($selected?'<span class="appearance-demo-selected">Selected</span>':'')
                    .'</div>';
                if($link!==null){
                    $html.='<a class="appearance-demo-link" href="'.self::e($link).'" target="_blank" rel="noopener noreferrer"'
                        .' aria-label="Open '.self::e($name).' '.self::e($surface).' preview">'
                        .'Preview design <span aria-hidden="true">↗</span></a>';
                }else{
                    $html.='<p class="appearance-demo-note">'
                        .($surface==='auth'?'Save the theme and open Sign in in a private window.':'Publish an edition to preview reader.').'</p>';
                }
                $html.='<button type="button" class="appearance-demo-choose" data-theme-choose'
                    .' data-theme-surface="'.self::e($surface).'" data-theme-name="'.self::e($name).'"'
                    .' aria-label="Select '.self::e($name).' '.self::e($surface).' theme in form">'
                    .($selected?'Selected in form':'Select this theme').'</button>';
                $html.='</article>';
            }
            $html.='</div></section>';
        }
        return $html.'<p class="appearance-studio-instruction" data-theme-selection-note aria-live="polite">'
            .'Selecting a design updates the form above. Click Save Appearance to publish it.</p></section>';
    }

    private static function mockup(string $surface,string $slug): string
    {
        $base='<div class="appearance-demo-screen appearance-demo-'.self::e($surface)
            .' appearance-demo-theme-'.self::e($slug).'" role="img" aria-label="'.self::e($surface).' interface illustration">'
            .'<div class="appearance-demo-browser"><span></span><span></span><span></span><i>ePaper</i></div>';
        if($surface==='admin'){
            return $base.'<div class="appearance-demo-shell"><div class="appearance-demo-sidebar">'
                .'<b></b><i></i><i></i><i></i><i></i><i></i></div>'
                .'<div class="appearance-demo-content"><div class="appearance-demo-heading"></div>'
                .'<div class="appearance-demo-stat-row"><i></i><i></i><i></i></div>'
                .'<div class="appearance-demo-chart"><span></span></div>'
                .'<div class="appearance-demo-line"></div></div></div></div>';
        }
        if($surface==='reader'){
            return $base.'<div class="appearance-demo-reader-body"><div class="appearance-demo-reader-pages">'
                .'<i></i><i></i><i></i></div><div class="appearance-demo-reader-stage">'
                .'<div class="appearance-demo-reader-toolbar"><span></span><span></span></div>'
                .'<div class="appearance-demo-reader-paper"><b>NEWS</b><i></i><i></i><i></i><i></i></div>'
                .'</div></div></div>';
        }
        if($surface==='auth'){
            return $base.'<div class="appearance-demo-auth-body"><div class="appearance-demo-auth-aside"></div>'
                .'<div class="appearance-demo-auth-card"><b>Sign in</b><i></i><i></i><i></i><span></span></div>'
                .'</div></div>';
        }
        return $base.'<div class="appearance-demo-webhero"><span>YOUR DAILY NEWSPAPER</span>'
            .'<b>Stories that matter.</b><i></i><i></i></div>'
            .'<div class="appearance-demo-covers"><i></i><i></i><i></i><i></i></div></div>';
    }

    private static function slug(string $name): string
    {
        return strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-', $name),'-'));
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }
}
