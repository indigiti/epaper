<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$pass=function(bool $ok,string $name)use(&$fails){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fails[]=$name;};
$pass(trim((string)@file_get_contents($root.'/VERSION'))==='1.1.5','VERSION 1.1.5');
$pass(!is_file($root.'/config/config.php')&&!is_file($root.'/config/runtime.php'),'no production config/secrets packaged');
$app=(string)file_get_contents($root.'/app/App.php');$view=(string)file_get_contents($root.'/app/Core/View.php');$css=(string)file_get_contents($root.'/public_html/assets/app.css');$crud=(string)file_get_contents($root.'/public_html/assets/crud.js');$sw=(string)file_get_contents($root.'/public_html/service-worker.js');$clip=(string)file_get_contents($root.'/app/Services/ClipService.php');$qr=(string)file_get_contents($root.'/app/Services/QrCode.php');
// Mandatory v1.1.4.1 Source Page QR hotfix preservation.
$base=json_decode((string)file_get_contents($root.'/release/V1.1.4.1-QR-BASELINE-HASHES.json'),true)['sha256']??[];
foreach(['app/Services/QrCode.php','app/Services/ClipService.php'] as$f){$actual=hash_file('sha256',$root.'/'.$f);$pass(isset($base[$f])&&hash_equals((string)$base[$f],$actual),'v1.1.4.1 exact hash retained '.$f);}
foreach(['BRANDING_VERSION = \'source-qr-v1\'','sourcePageUrl','QrCode::drawImagick'] as$x)$pass(str_contains($clip,$x),'Source Page QR clipping behavior '.$x);
foreach(['clip-source-context','Source page','Scan to open'] as$x)$pass(str_contains($app,$x),'Source Page QR showcase '.$x);
$clipPage=$app;if(preg_match('/private function publicClip\(.*?private function clipFormatPrepare/s',$app,$cm))$clipPage=$cm[0];$pass(!str_contains($clipPage,'clip-expiry'),'v1.1.4.1 visible clipping expiry remains removed from showcase');
// Brand Assets.
foreach(['adminBrandSettings','enctype="multipart/form-data"','brand.logo_path','brand.logo_dark_path','brand.mark_path','brand.favicon_path','brand.apple_touch_icon_path','brand.pwa_icon_192_path','brand.pwa_icon_512_path','storeBrandAsset','removeBrandAsset','uploads/branding','getimagesize','new \\finfo','8*1024*1024'] as$x)$pass(str_contains($app,$x),'brand asset foundation '.$x);
foreach(['brand-logo-stack','brand-logo-dark','apple-touch-icon','crud.js?v=1.1.5'] as$x)$pass(str_contains($view,$x),'brand rendering '.$x);
foreach(['brand-asset-grid','brand-asset-card','brand-upload-button','record-menu','record-popover','library-card-wrap'] as$x)$pass(str_contains($css,$x),'CRUD/brand UI '.$x);
foreach(['data-confirm','data-brand-file','data-brand-remove','data-brand-color','record-menu'] as$x)$pass(str_contains($crud,$x),'CRUD interaction '.$x);
// CRUD route/action families.
foreach([
'/admin/publications/','adminPublicationAction','/admin/highlights/','adminHighlightAction','/admin/master-data/','adminMasterAction','/admin/editions/','adminEditionAction','/admin/subscribers/','adminSubscriberStatus',
'/account/saved-articles/','accountSavedAction','/account/bookmarks/','accountBookmarkAction','/account/clips/','accountClipAction','/account/history/','accountHistoryDelete','/account/offline/','accountOfflineDelete','/account/notifications/','accountNotificationAction','accountClear'
] as$x)$pass(str_contains($app,$x),'CRUD action '.$x);
foreach(['publication.deleted','highlight.deleted','master_data.deleted','edition.archived','edition.restored','subscriber.status.updated','saved_article.deleted','bookmark.deleted','clip.deleted','history.deleted','offline.deleted','notification.'] as$x)$pass(str_contains($app,$x),'CRUD audit event '.$x);
// Tenant scope: all destructive SQL added in App.php must carry tenant_guid.
preg_match_all('/Store::exec\(\'((?:DELETE FROM|UPDATE) [^\']+)\'/i',$app,$m);foreach($m[1] as$sql){if(str_starts_with(strtoupper($sql),'DELETE FROM'))$pass(str_contains(strtolower($sql),'tenant_guid'),'tenant-scoped DELETE '.substr($sql,0,72));}
$pass(!str_contains($app,'DELETE FROM ep_audit_events'),'audit log remains immutable');$pass(!str_contains($app,'DELETE FROM ep_error_events'),'error history remains immutable');$pass(!str_contains($app,'DELETE FROM ep_processing_jobs'),'processing history remains immutable');
// Cache/version alignment.
$pass(str_contains($sw,"epaper-saas-v1.1.5")&&str_contains($sw,'/assets/crud.js?v=1.1.5'),'service worker aligned to v1.1.5');$pass(str_contains($view,'version:"1.1.5"'),'runtime version exposed as v1.1.5');
// Release artifacts.
foreach(['ROUTES-DELTA-1.1.5.json','CAPABILITIES-DELTA-1.1.5.json','CRUD-MATRIX-1.1.5.md','BUILD-MANIFEST-1.1.5.md','ROLLBACK-1.1.5.md'] as$f)$pass(is_file($root.'/release/'.$f),'release artifact '.$f);
if($fails){fwrite(STDERR,"\nBUILD GUARD FAILED: ".implode(', ',$fails)."\n");exit(1);}echo "\nALL v1.1.5 BUILD GUARDS PASSED\n";
