<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];
$pass=function(bool $ok,string $name)use(&$fails){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fails[]=$name;};
$pass(trim((string)@file_get_contents($root.'/VERSION'))==='1.1.6','VERSION 1.1.6');
$pass(!is_file($root.'/config/config.php')&&!is_file($root.'/config/runtime.php'),'no production config/secrets packaged');
$app=(string)file_get_contents($root.'/app/App.php');
$view=(string)file_get_contents($root.'/app/Core/View.php');
$css=(string)file_get_contents($root.'/public_html/assets/app.css');
$sw=(string)file_get_contents($root.'/public_html/service-worker.js');
$clip=(string)file_get_contents($root.'/app/Services/ClipService.php');
$base=json_decode((string)file_get_contents($root.'/release/V1.1.4.1-QR-BASELINE-HASHES.json'),true)['sha256']??[];
foreach(['app/Services/QrCode.php','app/Services/ClipService.php'] as $f){$actual=hash_file('sha256',$root.'/'.$f);$pass(isset($base[$f])&&hash_equals((string)$base[$f],$actual),'v1.1.4.1 exact QR baseline hash retained '.$f);}
foreach(["BRANDING_VERSION = 'source-qr-v1'",'sourcePageUrl','QrCode::drawImagick'] as $x)$pass(str_contains($clip,$x),'Source Page QR behavior '.$x);
foreach(['clip-source-context','Source page','Scan to open'] as $x)$pass(str_contains($app,$x),'Source Page QR showcase '.$x);
foreach(['adminBrandSettings','storeBrandAsset','removeBrandAsset','uploads/branding','adminPublicationAction','adminHighlightAction','adminMasterAction','accountClipAction'] as $x)$pass(str_contains($app,$x),'v1.1.5 CRUD/brand behavior '.$x);
foreach(['private function postedSetting','settings[brand.site_name]',"str_replace(['.',' '],'_',\$key)",'formnovalidate data-brand-remove'] as $x)$pass(str_contains($app,$x),'v1.1.5.1 settings normalization '.$x);
$pass(!str_contains($app,"\$_POST['brand.site_name']??''"),'no impossible direct dotted brand POST read');
foreach(['/social/edition/','socialPageThumb',"asset=\$assets['THUMB']??\$assets['PREVIEW']",'Cache-Control: public, max-age=86400','ETag: ','readfile($full)'] as $x)$pass(str_contains($app,$x),'thumbnail social delivery '.$x);
if(preg_match('/private function socialPageThumb\(.*?\n    private function /s',$app,$m)){$socialMethod=$m[0];$pass(!preg_match('/Imagick|file_put_contents|mkdir|writeImage|copy\(/',$socialMethod),'social thumbnail route performs no generated-file writes');}else{$pass(false,'socialPageThumb method located');}
foreach(['social_image','social_image_alt','canonical_url','og_title','og_description','og_type'] as $x)$pass(str_contains($app,$x),'Reader/clip social metadata option '.$x);
foreach(['property="og:title"','property="og:description"','property="og:url"','property="og:image"','property="og:image:alt"','name="twitter:card"','name="twitter:image"','rel="canonical"'] as $x)$pass(str_contains($view,$x),'Open Graph/Twitter markup '.$x);
$pass(str_contains($app,"\$socialAsset=\$assets['THUMB']??\$assets['PREVIEW']"),'Reader social image prefers existing THUMB');
$pass(str_contains($app,"\$clipSocial=\$private?'':Http::origin()"),'Private clip does not expose clipping social image');
foreach(['$logoAlt','self::e($logoAlt)','brand-with-logo','brand-name','aria-hidden="true"'] as $x)$pass(str_contains($view,$x),'accessible brand header '.$x);
foreach(['max-width:140px!important','max-height:34px!important','max-width:108px!important','max-width:82px!important','font-size:15px'] as $x)$pass(str_contains($css,$x),'responsive masthead CSS '.$x);
$pass(str_contains($sw,'epaper-saas-v1.1.6')&&str_contains($sw,'/assets/app.css?v=1.1.6'),'service worker aligned to v1.1.6');
$pass(str_contains($view,'version:"1.1.6"')&&str_contains($view,'app.css?v=1.1.6'),'runtime/assets aligned to v1.1.6');
if($fails){fwrite(STDERR,"\nBUILD GUARD FAILED: ".implode(', ',$fails)."\n");exit(1);}echo "\nALL v1.1.6 BUILD GUARDS PASSED\n";
