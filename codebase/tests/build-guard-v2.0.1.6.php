<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$ok=function(bool$c,string$m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};$read=fn(string$f)=>(string)@file_get_contents($root.'/'.$f);
$version=trim($read('VERSION'));$sw=$read('public_html/service-worker.js');$app=$read('app/App.php');$auth=$read('app/Core/Auth.php');$mobile=$read('app/Services/MobileAuth.php');$pipeline=$read('app/Services/ProcessingPipeline.php');$direct=$read('app/Services/DirectRecovery.php');$android=$read('mobile-android/app/build.gradle');$workflow=$read('.github/workflows/build-android-apk.yml');
$ok($version==='2.0.1.6','VERSION 2.0.1.6');
$ok(str_contains($sw,"const VERSION='epdi-v2.0.1.6'"),'Service Worker aligned');
$ok(is_file($root.'/BUILD-MANIFEST-2.0.1.6.md')&&is_file($root.'/UPDATE-2.0.1.6.md')&&is_file($root.'/MOBILE-AUTH-2.0.1.6.md'),'v2.0.1.6 release documentation packaged');
$ok(is_file($root.'/database/upgrade-v2.0.1.5-mobile-auth.sql'),'mobile-auth prerequisite migration retained');
$ok(str_contains($auth,"'memberships'=>[]")&&str_contains($auth,"'auth_source'=>'LOCAL_MOBILE'"),'local mobile remains outside admin RBAC');
$ok(str_contains($auth,'account_status')&&str_contains($auth,'subscriber_status')&&str_contains($auth,'identity_status'),'local account revocation hardening packaged');
$ok(str_contains($app,"/api/v1/auth/mobile/bootstrap")&&str_contains($app,"MOBILE_AUTH_PENDING"),'mobile auth API and SNA retry signal packaged');
$ok(str_contains($mobile,'pruneChallenges')&&str_contains($mobile,'ErrorCenter::record'),'mobile challenge retention and provider sanitization packaged');
$ok(str_contains($pipeline,'enqueueEditionRender')&&str_contains($pipeline,'auto_rescue_parent_pipeline'),'bounded processing recovery retained');
$ok(str_contains($direct,'direct-recovery-worker')||is_file($root.'/bin/direct-recovery-worker.php'),'direct recovery foundation retained');
$ok(str_contains($android,'compileSdk 36')&&str_contains($android,'targetSdk 36')&&str_contains($android,"versionName '2.0.1.6'"),'Android API/version aligned');
$ok(str_contains($workflow,'bundleRelease')&&str_contains($workflow,'EPDI_UPLOAD_KEYSTORE_B64'),'Android release AAB CI packaged');
$manifest=json_decode($read('manifest.json'),true);$ok(is_array($manifest)&&($manifest['version']??'')==='2.0.1.6','manifest.json aligned');
if($fails){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$fails)."\n");exit(1);}echo"\nPASS v2.0.1.6 guard\n";
