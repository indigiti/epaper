<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    if(!str_starts_with($class,'EpaperSaas\\'))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen('EpaperSaas\\'))).'.php';
    if(is_file($file))require_once $file;
});
use EpaperSaas\Services\PwaManifest;
function checkPwa(bool $ok,string $label):void{
    if(!$ok){fwrite(STDERR,"FAIL: ".$label."\n");exit(1);}
    echo "PASS: ".$label."\n";
}
$a='aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
$b='bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb';
$dir=sys_get_temp_dir().'/epdi-pwa-'.bin2hex(random_bytes(5));
mkdir($dir.'/uploads/branding/'.$a,0770,true);
foreach(['pwa-192.png','pwa-512.png','pwa-maskable-512.png'] as $filename)
    file_put_contents($dir.'/uploads/branding/'.$a.'/'.$filename,'fixture');
try{
 $s=['brand.site_name'=>'ePaper SaaS',
     'brand.pwa_icon_192_path'=>'/uploads/branding/'.$a.'/pwa-192.png?v=123',
     'brand.pwa_icon_512_path'=>'/uploads/branding/'.$a.'/pwa-512.png?v=123',
     'brand.pwa_maskable_512_path'=>'/uploads/branding/'.$a.'/pwa-maskable-512.png?v=123',
     'brand.accent'=>'#AABBCC'];
 $x=PwaManifest::build($s,'DigiTi ePaper',$a,$dir);
 $y=PwaManifest::build($s,'DCN News ePaper',$b,$dir);
 checkPwa($x['name']==='DigiTi ePaper'&&$y['name']==='DCN News ePaper','Generic app names replaced per publisher');
 checkPwa($x['id']==='/'&&$y['id']==='/'&&$x['scope']==='/'&&$y['start_url']==='/','Stable per-origin installed app ID and scope');
 checkPwa(str_contains($x['icons'][0]['src'],$a)&&$y['icons'][0]['src']==='/assets/icon-192.png','Tenant icons cannot cross publisher boundaries');
 checkPwa(count($x['icons'])===3&&count($y['icons'])===2,'Maskable icon only included when present for tenant');
 $s['brand.pwa_name']='DCN News PWA';$s['brand.pwa_short_name']='DCN';$s['brand.pwa_description']='Read verified news';
 $z=PwaManifest::build($s,'Ignored',$a,$dir);
 checkPwa($z['name']==='DCN News PWA'&&$z['short_name']==='DCN'&&$z['description']==='Read verified news','Explicit app identity preferences');
 $s['brand.pwa_icon_192_path']='https://external.invalid/image.png';
 $s['brand.pwa_icon_512_path']='/uploads/branding/'.$b.'/pwa-512.png';
 $s['brand.accent']='bad';
 $z=PwaManifest::build($s,'Ignored',$a,$dir);
 checkPwa($z['icons'][0]['src']==='/assets/icon-192.png'&&$z['icons'][1]['src']==='/assets/icon-512.png'
    &&$z['theme_color']==='#2563eb','Invalid asset URLs and theme colors safely fall back');
 checkPwa(PwaManifest::revision($s,'DigiTi',$a)!==PwaManifest::revision($s,'DCN',$b),'Manifest version is tenant-scoped');
 $app=file_get_contents($root.'/app/App.php');
 $view=file_get_contents($root.'/app/Core/View.php');
 $ht=file_get_contents($root.'/public_html/.htaccess');
 $sw=file_get_contents($root.'/public_html/service-worker.js');
 checkPwa(str_contains($app,"PwaManifest::send()")&&str_contains($view,'/pwa-manifest.webmanifest')
    &&str_contains($view,'PwaManifest::revision'),'Manifest routed through verified PHP tenant context');
 checkPwa(str_contains($ht,'RewriteRule ^manifest\.webmanifest$ index.php'),'Legacy manifest explicitly routed through PHP');
 checkPwa(str_contains($sw,"url.pathname==='/pwa-manifest.webmanifest'")&&
    !str_contains($sw,"'/manifest.webmanifest?v="),'SW never precaches tenant manifest');
 checkPwa(!is_file($root.'/public_html/manifest.webmanifest'),'Shared hardcoded manifest removed');
}finally{
 foreach(glob($dir.'/uploads/branding/'.$a.'/*')?:[] as $f)@unlink($f);
 @rmdir($dir.'/uploads/branding/'.$a);@rmdir($dir.'/uploads/branding');@rmdir($dir.'/uploads');@rmdir($dir);
}
echo "PASS PWA tenant isolation contract\n";
