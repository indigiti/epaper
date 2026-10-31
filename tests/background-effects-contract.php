<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='EpaperSaas\\';
    if(!str_starts_with($class,$prefix))return;
    $path=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require_once $path;
});
use EpaperSaas\Services\BackgroundEffects as Fx;
use EpaperSaas\Services\BackgroundEffectsStudio as Studio;
use EpaperSaas\Services\AdminRoutePolicy;
use EpaperSaas\Services\AdminFeatureCatalog;

$check=static function(bool $ok,string $title):void{
    echo ($ok?'PASS ':'FAIL ').$title.PHP_EOL;
    if(!$ok)throw new RuntimeException($title);
};
$check(Fx::EFFECTS===['Off','Halo','Waves','Net','Dots','Rings','Clouds','Cells','Fog'],
    'All eight Vanta effects plus disabled state are registered');
foreach(Fx::EFFECTS as $effect){
    $asset=Fx::asset($effect);
    if($effect==='Off'){
        $check($asset===null,'Off has no effect asset');
        continue;
    }
    $check(is_string($asset)&&preg_match('~^/assets/vendor/vanta\.[a-z]+\.0\.5\.24\.min\.js$~',$asset)===1,
        $effect.' resolves only to pinned same-origin Vanta asset');
    $check(is_file($root.'/public_html'.$asset),$effect.' vendored binary exists');
    $check(filesize($root.'/public_html'.$asset)<30000,$effect.' Vanta asset stays under 30 KB');
}
$default=Fx::defaults();
$check($default['appearance.home_background_effect']==='Off','New and unconfigured publishers retain no animation');
$check(count(Fx::FIELDS)>=25,'Effect-specific color, motion and geometry controls are registered');
$check(count($default)===count(Fx::FIELDS)+1,'Exactly one saved value per supported control');
$check(Fx::normalize(Fx::normalize($default))===Fx::normalize($default),
    'Default values normalize consistently and idempotently');
$rejected=0;
foreach([
    ['appearance.home_background_effect'=>'javascript:alert(1)'],
    ['home_effect.base_color'=>'rgba(0,0,0,1)'],
    ['home_effect.secondary_color'=>'#fff'],
    ['home_effect.cloud_color'=>'<script>'],
    ['home_effect.fog_blur'=>'99'],
    ['home_effect.net_points'=>'1000000'],
    ['home_effect.x_offset'=>'-3'],
    ['home_effect.speed'=>'infinity'],
    ['home_effect.opacity'=>'1'],
    ['home_effect.dots_lines'=>'yes'],
] as $changes){
    try{Fx::normalize(array_replace($default,$changes));}
    catch(\InvalidArgumentException $e){$rejected++;continue;}
}
$check($rejected===10,'Unsupported JS/CSS, invalid values and GPU-heavy settings are rejected');
foreach(Fx::EFFECTS as $effect){
    $input=$default;
    $input['appearance.home_background_effect']=$effect;
    $value=Fx::normalize($input);
    $check($value['appearance.home_background_effect']===$effect,$effect.' survives settings validation');
}
$config=Fx::browserOptions($default);
foreach(['skyColor','cloudColor','cloudShadowColor','sunColor',
    'fogHighlight','fogMidtone','fogLowlight','blurFactor'] as $key){
    $check(array_key_exists($key,$config),'Browser preview supports '.$key);
}
$ui=Studio::render($default,'<input type="hidden" name="_csrf" value="test-token">');
$check(str_contains($ui,'data-effect-studio'),'Dedicated effect studio renders correctly');
$check(str_contains($ui,'value="test-token"'),'The actual CSRF form control is preserved');
foreach(Fx::EFFECTS as $effect){
    $check(str_contains($ui,'data-effect-select="'.$effect.'"'),$effect.' preview card is present');
}
$check(substr_count($ui,'data-effect-select=')===count(Fx::EFFECTS),'Exact effect card count matches registry');
$check(str_contains($ui,'data-effect-preview')&&str_contains($ui,'data-effect-reset'),
    'Preview and reset controls are present');
$check(str_contains($ui,'Save background effect'),'Publishing requires explicit Save');
$check(!str_contains($ui,'https://cdn'),'No runtime third-party CDN dependencies');
$check(str_contains($ui,'vanta.fog.0.5.24.min.js')&&str_contains($ui,'vanta.rings.0.5.24.min.js'),
    'Live preview references pinned assets');
$check(AdminRoutePolicy::allows('/admin/settings/effects','OWNER'),'Owner allowed');
$check(AdminRoutePolicy::allows('/admin/settings/effects','PUBLISHER_ADMIN'),'Publisher manager allowed');
$check(!AdminRoutePolicy::allows('/admin/settings/effects','EDITOR'),'Editor denied direct settings route');
$check(!AdminRoutePolicy::allows('/admin/settings/effects','OPERATOR'),'Operator denied direct settings route');
$all=AdminFeatureCatalog::flatten(AdminFeatureCatalog::groups('PUBLISHER_ADMIN',true,true));
$shown=array_filter($all,static fn(array $a):bool=>$a[1]==='/admin/settings/effects');
$check(count($shown)===1,'Background Effects appears once in publisher manager navigation');
$check(!array_filter(AdminFeatureCatalog::flatten(AdminFeatureCatalog::groups('EDITOR',true,false)),
    static fn(array $a):bool=>$a[1]==='/admin/settings/effects'),'Editors never see effect settings');
$app=(string)file_get_contents($root.'/app/App.php');
$view=(string)file_get_contents($root.'/app/Core/View.php');
$check(str_contains($app,"'effects')\$this->adminEffectsSettings(\$method)"),
    'Effects route receives dedicated handler and validation');
$check(str_contains($app,"if(\$method==='POST')\$this->adminSettingsGroup('appearance',\$method);"),
    'Legacy settings POST cannot bypass group allowlists or effects validation');

$check(str_contains($view,'admin-effects.css')&&str_contains($view,'admin-effects.js'),
    'Customizer is loaded only in designated admin surface');
$check(str_contains($view,'vanta-config.js'),'Public and admin Vanta share validated settings map');

if(in_array('--fixtures',$argv,true)){
    $folder=$root.'/tests/effects-studio-fixtures';
    if(!is_dir($folder)&&!mkdir($folder,0775,true)&&!is_dir($folder))throw new RuntimeException('Cannot create fixtures directory');
    $css=(string)file_get_contents($root.'/public_html/assets/app.css')."\n".
        (string)file_get_contents($root.'/public_html/assets/admin-beta.css')."\n".
        (string)file_get_contents($root.'/public_html/assets/admin-design-system.css')."\n".
        (string)file_get_contents($root.'/public_html/assets/admin-effects.css');
    $html='<!doctype html><html lang="en"><head><meta charset="utf-8">'.
        '<meta name="viewport" content="width=device-width,initial-scale=1"><style>'.
        $css.'</style></head><body class="admin-shell">'.
        '<div class="app-workspace"><aside class="app-sidebar"><div class="sidebar-context">Publishing Studio</div>'.
        '<nav class="app-nav"><a class="app-nav-link" href="/admin/settings/appearance">Appearance</a>'.
        '<a class="app-nav-link active" href="/admin/settings/effects">Background Effects</a></nav></aside>'.
        '<main class="workspace-main">'.$ui.'</main></div></body></html>';
    file_put_contents($folder.'/effects-studio.html',$html);
}
echo 'PASS Background Effects contract'.PHP_EOL;
