<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];
$pass=function(bool $ok,string $name)use(&$fails){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fails[]=$name;};
$pass(trim((string)@file_get_contents($root.'/VERSION'))==='1.1.7','VERSION 1.1.7');
$pass(!is_file($root.'/config/config.php')&&!is_file($root.'/config/runtime.php'),'no production config/secrets packaged');
$app=(string)file_get_contents($root.'/app/App.php');$view=(string)file_get_contents($root.'/app/Core/View.php');$css=(string)file_get_contents($root.'/public_html/assets/app.css');$crud=(string)file_get_contents($root.'/public_html/assets/crud.js');$guest=(string)file_get_contents($root.'/public_html/assets/guest-clipping.js');$sw=(string)file_get_contents($root.'/public_html/service-worker.js');$clip=(string)file_get_contents($root.'/app/Services/ClipService.php');
$base=json_decode((string)file_get_contents($root.'/release/V1.1.4.1-QR-BASELINE-HASHES.json'),true)['sha256']??[];
foreach(['app/Services/QrCode.php','app/Services/ClipService.php'] as$f){$actual=hash_file('sha256',$root.'/'.$f);$pass(isset($base[$f])&&hash_equals((string)$base[$f],$actual),'v1.1.4.1 exact QR baseline hash retained '.$f);}
foreach(["BRANDING_VERSION = 'source-qr-v1'",'sourcePageUrl','QrCode::drawImagick'] as$x)$pass(str_contains($clip,$x),'Source Page QR behavior '.$x);
foreach(['private function postedSetting','settings[brand.site_name]',"str_replace(['.',' '],'_',\$key)",'formnovalidate data-brand-remove'] as$x)$pass(str_contains($app,$x),'v1.1.5.1 settings normalization '.$x);
foreach(['/social/edition/','socialPageThumb',"asset=\$assets['THUMB']??\$assets['PREVIEW']",'Cache-Control: public, max-age=86400'] as$x)$pass(str_contains($app,$x),'v1.1.6 smart sharing '.$x);
foreach(['$logoAlt','brand-with-logo','brand-name-fallback',"\$brandText=''"] as$x)$pass(str_contains($view,$x),'v1.1.6.1 header dedup '.$x);
// Bulk clear regression.
foreach(['accountRemovableCount','deleted_count','library_type','cleanupClipFiles','account.clip.file_cleanup.failed'] as$x)$pass(str_contains($app,$x),'bulk account clear '.$x);
$pass(str_contains($app,"Audit::log('account.library.cleared','account',null"),'bulk clear audit uses null resource GUID');
$pass(!str_contains($app,"Audit::log('account.library.cleared','account',\$pathType"),'invalid pathType audit resource removed');
$pass(str_contains($app,"DELETE FROM ep_clips WHERE tenant_guid=? AND platform_user_guid=?"),'clip bulk delete tenant/user scoped');
$pass(str_contains($app,"?cleared=1&count="),'bulk clear redirects with affected count');
$pass(str_contains($app,'$removableCount>0?'),'Clear all shown only with removable records');
// Master Data workspace + shared drawer.
foreach(['master-tabs','data-master-tab="city"','data-master-tab="language"','data-master-tab="section"','master-data-panel','data-open-master-create','data-master-search','data-editor-title'] as$x)$pass(str_contains($app,$x),'Master Data UX '.$x);
foreach(['record-editor-layer','openForm','data-open-record-editor','data-open-master-create','activateMaster','data-master-search'] as$x)$pass(str_contains($crud,$x),'shared CRUD drawer JS '.$x);
foreach(['record-editor','master-tabs','master-data-panel.active','master-record-main','record-editor-layer.hidden'] as$x)$pass(str_contains($css,$x),'shared CRUD drawer CSS '.$x);
$pass(str_contains($crud,"const form=$('.record-form',menu)")&&str_contains($crud,"form.classList.add('editor-source')"),'form-heavy record menus converted to Edit action');
$pass(str_contains($app,"Http::redirect('/admin/master-data?tab='.\$type.'&updated=1')"),'Master Data action returns to active tab');
$pass(str_contains($app,"(int)(\$_POST['sort_order']??0)") ,'section creation supports sort order');
// Protected history remains undeletable via admin CRUD routes.
$pass(!str_contains($app,"/admin/audit/delete")&&!str_contains($app,"/admin/errors/delete")&&!str_contains($app,"/admin/processing/delete"),'audit/error/processing deletion remains prohibited');
$pass(str_contains($sw,'epaper-saas-v1.1.7')&&str_contains($sw,'/assets/crud.js?v=1.1.7'),'service worker aligned to v1.1.7');
$pass(str_contains($view,'version:"1.1.7"')&&str_contains($view,'app.css?v=1.1.7')&&str_contains($view,'crud.js?v=1.1.7'),'runtime/assets aligned to v1.1.7');
// v1.1.7 Guest Clipping + Conversion Gate.
foreach(['guestClipUsed','guestClipMarkUsed','claimGuestClipAfterLogin','GUEST_CLIP_LIMIT','GUEST_CLIP_RATE_LIMIT','guest-clip-ip:','quota_remaining','guest_clip.created','guest_clip.claimed'] as$x)$pass(str_contains($app,$x),'v1.1.7 guest clipping backend '.$x);
$pass(str_contains($app,"ClipService::create((string)(\$d['page_guid']??''),(float)(\$d['x']??0),(float)(\$d['y']??0),(float)(\$d['width']??0),(float)(\$d['height']??0),isset(\$d['title'])?(string)\$d['title']:null,'PUBLIC')"),'guest clipping is always link-shareable PUBLIC');
$pass(str_contains($app,"UPDATE ep_clips SET platform_user_guid=?")&&str_contains($app,'platform_user_guid IS NULL'),'guest clip claim is ownership-safe and only claims ownerless clip');
$pass(str_contains($app,"data-reader-guest")&&str_contains($app,'data-guest-clip-used')&&str_contains($app,'data-clip-subscription-required'),'Reader exposes guest/subscription conversion state');
$pass(str_contains($app,'Your free guest clipping')&&str_contains($app,'guest-clip-gate')&&str_contains($app,'guest-claim-banner'),'guest conversion UI rendered');
$pass(str_contains($view,"\$opts['robots']??''"),'View supports route-level robots override');
$pass(str_contains($app,"'robots'=>\$guestClip?'noindex,nofollow':null"),'guest clipping pages are noindex/nofollow');
foreach(['guest clipping conversion gate','guestClipUsed','ENTITLEMENT_REQUIRED','GUEST_CLIP_LIMIT','data-clip-save'] as$x)$pass(str_contains($guest,$x),'guest clipping frontend '.$x);
foreach(['guest-free-clip-note','guest-clip-gate','guest-claim-banner'] as$x)$pass(str_contains($css,$x),'guest clipping styling '.$x);
$pass(str_contains($sw,'epaper-saas-v1.1.7')&&str_contains($sw,'/assets/guest-clipping.js?v=1.1.7'),'service worker caches guest clipping module');
$pass(str_contains($view,'guest-clipping.js?v=1.1.7')&&str_contains($view,'version:"1.1.7"'),'Reader/runtime aligned to v1.1.7');
if($fails){fwrite(STDERR,"\nBUILD GUARD FAILED: ".implode(', ',$fails)."\n");exit(1);}echo "\nALL v1.1.7 BUILD GUARDS PASSED\n";
