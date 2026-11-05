<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='EpaperSaas\\';if(!str_starts_with($class,$prefix))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file))require_once $file;
});
use EpaperSaas\Services\BrandIdentity;
use EpaperSaas\Services\PwaManifest;
function checkBrand(bool $ok,string $label):void {
    if(!$ok){fwrite(STDERR,"FAIL: ".$label."\n");exit(1);}
    echo "PASS: ".$label."\n";
}
checkBrand(BrandIdentity::websiteName(['brand.site_name'=>'Pune Times Mirror'],'LEX-EP')==='Pune Times Mirror','Configured website identity is authoritative');
checkBrand(BrandIdentity::websiteName(['brand.site_name'=>'ePaper SaaS'],'Lexicon Publishing')==='Lexicon Publishing','Generic placeholder falls back to verified tenant');
checkBrand(BrandIdentity::websiteName(['brand.site_name'=>'   '],'DCN News')==='DCN News','Blank legacy name uses verified tenant');
checkBrand(BrandIdentity::websiteName(['brand.site_name'=>''], 'ePaper SaaS')==='ePaper','Generic tenant never becomes public site name');
checkBrand(PwaManifest::name(['brand.site_name'=>'Pune Times Mirror'],'LEX-EP')==='Pune Times Mirror','PWA inherits website identity');
checkBrand(PwaManifest::name(['brand.site_name'=>'Pune Times Mirror','brand.pwa_name'=>'Pune Mirror App'],'LEX-EP')==='Pune Mirror App','Independent PWA name overrides website label');
$tenant=file_get_contents($root.'/app/Services/TenantData.php');$view=file_get_contents($root.'/app/Core/View.php');
checkBrand(str_contains($tenant,'BrandIdentity::websiteName('),'Tenant read-model centralizes identity');
checkBrand(str_contains($view,"if(!empty(\$ctx['tenant_guid']))\$brand=(string)TenantData::brand()['site_name']"),'All tenant-bound layouts use canonical brand');
checkBrand(str_contains($view,"brand.mark_path")&&str_contains($view,'admin-sidebar-emblem'),'Sidebar displays tenant square mark');
echo "PASS publisher identity consistency contract\n";
