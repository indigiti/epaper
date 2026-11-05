<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='EpaperSaas\\';if(!str_starts_with($class,$prefix))return;
    $path=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require_once $path;
});
use EpaperSaas\Services\{PwaIconAssets,PwaManifest};
function iconCheck(bool $ok,string $label):void{
    if(!$ok){fwrite(STDERR,"FAIL: ".$label."\n");exit(1);}
    echo "PASS: ".$label."\n";
}
$tenant='aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
$other='bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb';
$dir=sys_get_temp_dir().'/epdi-pwa-logo-'.bin2hex(random_bytes(4));
mkdir($dir,0770,true);
$src=$dir.'/sample.png';
try {
    if(!function_exists('imagecreatetruecolor') && !class_exists('Imagick'))
        throw new RuntimeException('PWA icon acceptance needs GD or Imagick');
    if(function_exists('imagecreatetruecolor')){
        $image=imagecreatetruecolor(680,600);$color=imagecolorallocate($image,22,104,147);
        imagefilledrectangle($image,0,0,679,599,$color);
        imagepng($image,$src);imagedestroy($image);
    }else{
        $image=new Imagick();$image->newImage(680,600,new ImagickPixel('#166893'),'png');
        $image->writeImage($src);$image->clear();
    }
    $urls=PwaIconAssets::generate($src,$tenant,$dir);
    foreach(['brand.pwa_logo_path'=>'512','brand.pwa_icon_192_path'=>'192',
             'brand.pwa_icon_512_path'=>'512','brand.pwa_maskable_512_path'=>'maskable-512',
             'brand.apple_touch_icon_path'=>'apple-touch'] as $key=>$variant){
        $url=$urls[$key]??'';
        iconCheck(PwaIconAssets::validate($url,$tenant,$variant,$dir),
            'Generated '.$variant.' icon belongs to publisher');
        $dimensions=getimagesize($dir.$url);
        $expected=$variant==='apple-touch'?180:($variant==='192'?192:512);
        iconCheck(($dimensions[0]??0)===$expected&&($dimensions[1]??0)===$expected
            &&($dimensions['mime']??'')==='image/png','Valid '.$variant.' PNG dimensions and MIME');
        iconCheck(!PwaIconAssets::validate($url,$other,$variant,$dir),'No cross-tenant icon access');
    }
    $manifest=PwaManifest::build($urls+['brand.site_name'=>'Pune Times Mirror'],'LEX-EP',$tenant,$dir);
    iconCheck(PwaManifest::source($urls,$tenant,$dir)==='CUSTOM'&&count($manifest['icons'])===3,
        'Manifest includes verified standard and maskable icons');
    iconCheck($manifest['id']==='/'&&$manifest['start_url']==='/'&&$manifest['scope']==='/',
        'Changing logo retains existing installed app identity and scope');
    $small=$dir.'/small.png';
    if(function_exists('imagecreatetruecolor')){
        $img=imagecreatetruecolor(64,64);imagepng($img,$small);imagedestroy($img);
    }else{
        $img=new Imagick();$img->newImage(64,64,new ImagickPixel('white'),'png');$img->writeImage($small);$img->clear();
    }
    try{PwaIconAssets::generate($small,$tenant,$dir);throw new RuntimeException('Undersized icon was incorrectly accepted');}
    catch(RuntimeException $e){iconCheck(str_contains($e->getMessage(),'512 pixels'),'Reject tiny app icons before publishing');}
    iconCheck(!PwaIconAssets::validate('/uploads/branding/'.$other.'/pwa-custom-0123456789abcdef-512.png',$tenant,'512',$dir),'Foreign paths are never trusted');
    $app=file_get_contents($root.'/app/App.php');
    $view=file_get_contents($root.'/app/Core/View.php');
    iconCheck(str_contains($app,'name="pwa_logo"')&&str_contains($app,'PwaIconAssets::generate('),
        'Brand editor exposes dedicated PWA logo upload and processing');
    iconCheck(str_contains($view,'brand.apple_touch_icon_path'),'Apple home-screen uses tenant-managed icon');
    iconCheck(strpos($app,'$pendingPwaIcons=PwaIconAssets::generate(')<strpos($app,"TenantData::setSetting('brand.site_name'"),
        'PWA icon processing failures occur before publisher branding changes');
    iconCheck(str_contains($app,'pwa-icon-previews'),'PWA management previews standard and maskable sizes');
    iconCheck(str_contains($app,'Verified publisher: '),'Brand settings distinguish publisher identity from site label');
    iconCheck(str_contains($app,'restorePwaIconsFromMark('),'Removing PWA logo restores Square brand mark fallback');
}finally{
    foreach(glob($dir.'/uploads/branding/'.$tenant.'/*')?:[] as $file)@unlink($file);
    @rmdir($dir.'/uploads/branding/'.$tenant);@rmdir($dir.'/uploads/branding');@rmdir($dir.'/uploads');
    foreach(glob($dir.'/*.png')?:[] as $file)@unlink($file);
    @rmdir($dir);
}
echo "PASS dedicated PWA brand icon acceptance\n";
