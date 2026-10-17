<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$ok=function(bool$c,string$m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};$read=fn(string$f)=>(string)@file_get_contents($root.'/'.$f);
$version=trim($read('VERSION'));$sw=$read('public_html/service-worker.js');$app=$read('app/App.php');$auth=$read('app/Core/Auth.php');$mobile=$read('app/Services/MobileAuth.php');$device=$read('app/Services/DeviceAuth.php');$android=$read('mobile-android/app/src/main/java/in/digiti/epdi/MainActivity.java');$appGradle=$read('mobile-android/app/build.gradle');$rootGradle=$read('mobile-android/build.gradle');$workflow=$read('.github/workflows/build-android-apk.yml');$scheduler=$read('bin/scheduler.php');$manifest=json_decode($read('manifest.json'),true);
$ok($version==='2.0.1.7','VERSION is 2.0.1.7');
$ok(str_contains($sw,"const VERSION='epdi-v2.0.1.7';"),'Service Worker cache namespace matches release');
$ok(is_array($manifest)&&($manifest['version']??'')==='2.0.1.7'&&($manifest['database_upgrade']??'')==='database/upgrade-v2.0.1.7-flexible-reader-identity.sql','release manifest and migration match version');
$ok(str_contains($auth,"\$source==='LOCAL_MOBILE'")&&str_contains($auth,"\$source==='LOCAL_DEVICE'")&&str_contains($auth,'account_status')&&str_contains($auth,'subscriber_status')&&str_contains($auth,'identity_status'),'local mobile/device sessions validate account/subscriber/identity state');
$ok(str_contains($auth,"in_array(\$source,['LOCAL_MOBILE','LOCAL_DEVICE'],true)")&&str_contains($auth,'catch(\\Throwable)'),'local reader validation fails closed on store errors');
$ok(str_contains($app,'if(Auth::user())Auth::sessionAccessActive();'),'session access is revalidated after tenant resolution');
$ok(str_contains($mobile,'MobileAuthPendingException')&&str_contains($mobile,'60519'),'SNA provider pending state remains distinguished');
$ok(str_contains($android,'maxChecks = silent ? 4 : 1')&&str_contains($android,'MOBILE_AUTH_PENDING')&&str_contains($android,'Thread.sleep(retryMs)'),'Android retains bounded SNA retry/backoff');
$ok(str_contains($device,'hash_hmac')&&str_contains($device,'ep_local_device_accounts')&&str_contains($device,'mergeIntoMobile'),'temporary-device privacy, persistence and promotion packaged');
$ok(str_contains($scheduler,'MobileAuth::pruneChallenges')&&str_contains($scheduler,'DeviceAuth::prune'),'mobile/device maintenance wired to scheduler');
$ok(str_contains($appGradle,'compileSdk 36')&&str_contains($appGradle,'targetSdk 36')&&str_contains($appGradle,"versionName '2.0.1.7'"),'Android target/version aligned');
$ok(str_contains($rootGradle,"version '8.13.2'")&&str_contains($workflow,"gradle-version: '8.13'"),'Android build stack remains AGP 8.13.2 / Gradle 8.13');
$ok(str_contains($workflow,'bundleRelease')&&str_contains($workflow,'EPDI_UPLOAD_KEYSTORE_B64'),'CI retains release AAB with optional upload-key signing');
if($fails){fwrite(STDERR,"\nMOBILE AUTH / DEVICE HARDENING FAILED\n - ".implode("\n - ",$fails)."\n");exit(1);}echo"\nPASS v2.0.1.7 mobile/device hardening guard\n";
