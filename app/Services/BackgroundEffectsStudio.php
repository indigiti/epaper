<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

use EpaperSaas\Core\Build;

/** SSR editor for publisher-owned visual background effects. */
final class BackgroundEffectsStudio
{
    public static function render(array $saved,string $csrf,bool $wasSaved=false): string
    {
        $values=BackgroundEffects::normalize($saved);
        $current=$values['appearance.home_background_effect'];
        $names=['Halo'=>'Soft glowing rings','Waves'=>'Fluid editorial waves','Net'=>'Connected network','Dots'=>'Minimal dot field'];
        $descriptions=[
            'Halo'=>'Ambient rings with gentle motion',
            'Waves'=>'Low-contrast moving wave surface',
            'Net'=>'Subtle connecting lines and points',
            'Dots'=>'Ordered floating points',
        ];
        $encodedAssets=[];
        foreach(BackgroundEffects::EFFECTS as $name){
            if($name==='Off')continue;
            $encodedAssets[strtoupper($name)]=Build::asset((string)BackgroundEffects::asset($name));
        }
        $h='<section class="admin-head"><div><span class="eyebrow">VISUAL STUDIO</span>'
            .'<h1>Background Effects</h1><p class="muted">Tune your homepage motion and colours without affecting the newspaper reader.</p>'
            .'</div><a class="btn ghost" href="/admin/settings/appearance">Appearance settings</a></section>';
        $h.='<nav class="settings-tabs" aria-label="Publisher settings sections">';
        foreach(SettingsCatalog::groups() as $key=>$group){
            $h.='<a class="settings-tab'.($key==='effects'?' active':'').'" href="/admin/settings/'.self::e($key).'">'
                .self::e((string)$group['label']).'</a>';
        }
        $h.='</nav>'.($wasSaved?'<div class="alert success">Background effects saved. Refresh the public homepage to see the published configuration.</div>':'');
        $h.='<div class="effect-studio" data-effect-studio>'
            .'<form method="post" class="effect-controls panel" data-effect-form>'
            .$csrf
            .'<div class="effect-panel-heading"><span class="eyebrow">1. CHOOSE AN EFFECT</span>'
            .'<h2>Select animation</h2><p class="muted">Disabled by default. Only public homepage hero banners support effects.</p></div>'
            .'<label class="effect-main-select">Background effect'
            .'<select name="settings[appearance.home_background_effect]" data-effect-choice>';
        foreach(BackgroundEffects::EFFECTS as $name){
            $h.='<option value="'.self::e($name).'"'.($name===$current?' selected':'').'>'.self::e($name).'</option>';
        }
        $h.='</select></label><div class="effect-picker" aria-label="Effect choices">';
        foreach(BackgroundEffects::EFFECTS as $name){
            $id=strtolower($name);
            $h.='<button type="button" class="effect-choice-card'.($name===$current?' is-selected':'').'"'
                .' data-effect-select="'.self::e($name).'" aria-pressed="'.($name===$current?'true':'false').'">'
                .'<span class="effect-choice-icon effect-icon-'.self::e($id).'" aria-hidden="true"></span>'
                .'<strong>'.self::e($name).'</strong><small>'
                .self::e($name==='Off'?'Use the standard homepage':$descriptions[$name]).'</small></button>';
        }
        $h.='</div><div class="effect-panel-heading effect-customize-heading"><span class="eyebrow">2. CUSTOMIZE</span>'
            .'<h2>Fine-tune appearance</h2><p class="muted">Changes update this preview only. Press Save to publish.</p></div>';
        $h.='<div class="effect-fields">';
        foreach(BackgroundEffects::FIELDS as $key=>$field){
            [$label,$type,$default,$min,$max,$step,$applies]=$field;
            $value=(string)$values[$key];
            $h.='<label class="effect-field" data-effect-applies="'.self::e(implode(',',$applies)).'"'
                .' data-effect-field="'.self::e($key).'">'
                .'<span class="effect-field-label">'.self::e($label).'</span><span class="effect-field-input">';
            $name='settings['.$key.']';
            if($type==='color'){
                $h.='<input type="color" name="'.self::e($name).'" value="'.self::e($value).'"'
                    .' aria-label="'.self::e($label).'" data-effect-input><output>'.self::e($value).'</output>';
            }elseif($type==='select'){
                $h.='<select name="'.self::e($name).'" data-effect-input>';
                foreach(['On','Off'] as $option){
                    $h.='<option value="'.$option.'"'.($option===$value?' selected':'').'>'.$option.'</option>';
                }
                $h.='</select>';
            }else{
                $h.='<input type="range" name="'.self::e($name).'"'
                    .' min="'.self::e((string)$min).'" max="'.self::e((string)$max).'"'
                    .' step="'.self::e((string)$step).'" value="'.self::e($value).'"'
                    .' aria-label="'.self::e($label).'" data-effect-input>'
                    .'<output>'.self::e($value).'</output>';
            }
            $h.='</span></label>';
        }
        $h.='</div><div class="effect-form-actions">'
            .'<button type="button" class="btn ghost" data-effect-reset>Reset customizer</button>'
            .'<button class="btn primary" type="submit">Save background effect</button>'
            .'</div><p class="muted effect-form-note">This saves only your publisher’s theme configuration. The effect never changes newspaper pages, account, login or Admin layouts.</p>'
            .'</form>';
        $h.='<section class="effect-preview-panel panel" aria-labelledby="effect-preview-title">'
            .'<div class="effect-preview-heading"><div><span class="eyebrow">LIVE PREVIEW</span>'
            .'<h2 id="effect-preview-title">Homepage appearance</h2></div>'
            .'<div class="effect-preview-actions"><span class="effect-unsaved" data-effect-unsaved hidden>Unsaved</span>'
            .'<button type="button" class="btn ghost" data-effect-preview-toggle aria-pressed="true">Pause animation</button></div></div>'
            .'<div class="effect-preview-stage" data-effect-preview-stage>'
            .'<div class="effect-preview-canvas" data-effect-preview aria-hidden="true"></div>'
            .'<div class="effect-preview-gradient" aria-hidden="true"></div>'
            .'<div class="effect-preview-copy"><span class="effect-preview-kicker">TODAY’S EDITION</span>'
            .'<h3>Stories worth opening.</h3><p>A premium reading experience, without compromising clarity.</p>'
            .'<span class="effect-preview-cta">Read today’s paper</span></div>'
            .'<div class="effect-preview-cover" aria-hidden="true"><div class="effect-paper-heading">YOUR DAILY</div>'
            .'<div class="effect-paper-lines"></div><div class="effect-paper-image"></div><div class="effect-paper-lines short"></div></div>'
            .'</div><div class="effect-preview-status" role="status" aria-live="polite" data-effect-status>'
            .'Your preview is not published until you save.</div>'
            .'<div class="effect-preview-foot"><span>Desktop animation; static on mobile, reduced-motion and low-power devices</span>'
            .'<a href="/?effect_preview='.rawurlencode(strtolower($current)).'" data-effect-public-preview target="_blank" rel="noopener noreferrer">Open homepage preview</a></div>'
            .'</section></div>';
        $h.='<script type="application/json" id="epdi-effect-assets">'
            .json_encode(['three'=>Build::asset('/assets/vendor/three.r134.min.js'),'effects'=>$encodedAssets],
                JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES)
            .'</script>';
        return $h;
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
    }
}
