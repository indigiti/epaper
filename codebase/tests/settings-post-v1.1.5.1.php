<?php
$fail=[];$pass=function(bool $ok,string $name)use(&$fail){echo($ok?'PASS':'FAIL')." — $name\n";if(!$ok)$fail[]=$name;};
function postedSetting(string $key,mixed $default=null,?bool &$present=null): mixed
{
    $present=false;
    if(isset($_POST['settings'])&&is_array($_POST['settings'])&&array_key_exists($key,$_POST['settings'])){$present=true;return$_POST['settings'][$key];}
    if(array_key_exists($key,$_POST)){$present=true;return$_POST[$key];}
    $normalized=str_replace(['.',' '],'_',$key);
    if(array_key_exists($normalized,$_POST)){$present=true;return$_POST[$normalized];}
    return$default;
}

// New v1.1.5.1 nested form encoding preserves exact dotted keys for every settings family.
parse_str('settings%5Bappearance.admin_theme%5D=Publisher+Pro&settings%5Bbrand.site_name%5D=Pune+Mirror&settings%5Bbrand.accent%5D=%232563eb&settings%5Bseo.title_suffix%5D=ePaper&settings%5Breader.default_fit%5D=width&settings%5Bprocessing.pdf_dpi%5D=220&settings%5Bclipping.default_visibility%5D=PRIVATE',$_POST);
$cases=[
    'appearance.admin_theme'=>'Publisher Pro',
    'brand.site_name'=>'Pune Mirror',
    'brand.accent'=>'#2563eb',
    'seo.title_suffix'=>'ePaper',
    'reader.default_fit'=>'width',
    'processing.pdf_dpi'=>'220',
    'clipping.default_visibility'=>'PRIVATE',
];
foreach($cases as$key=>$expected){$v=postedSetting($key,'x',$present);$pass($present&&$v===$expected,'nested settings[] preserves '.$key);}

// Backward compatibility for an already-cached v1.1.5 page: PHP normalizes direct dotted field names.
parse_str('brand.site_name=Legacy+Publisher&appearance.admin_theme=Executive+Slate&seo.title_suffix=News&reader.default_fit=page&processing.pdf_dpi=240&clipping.default_visibility=PUBLIC',$_POST);
$legacy=[
    'brand.site_name'=>'Legacy Publisher',
    'appearance.admin_theme'=>'Executive Slate',
    'seo.title_suffix'=>'News',
    'reader.default_fit'=>'page',
    'processing.pdf_dpi'=>'240',
    'clipping.default_visibility'=>'PUBLIC',
];
foreach($legacy as$key=>$expected){$v=postedSetting($key,'x',$present);$pass($present&&$v===$expected,'legacy PHP-normalized field remains accepted for '.$key);}

$_POST=['settings'=>['brand.tagline'=>'']];$v=postedSetting('brand.tagline','fallback',$present);$pass($present&&$v==='','intentional empty optional setting is preserved');
$_POST=[];$v=postedSetting('brand.site_name','Existing Publisher',$present);$pass(!$present&&$v==='Existing Publisher','missing partial-update field falls back safely');
if($fail){fwrite(STDERR,"FAILED: ".implode(', ',$fail)."\n");exit(1);}echo"ALL SETTINGS POST NORMALIZATION CHECKS PASSED\n";
