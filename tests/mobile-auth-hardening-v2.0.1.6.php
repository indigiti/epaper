<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];
$ok=function(bool $condition,string $message)use(&$fails):void{echo($condition?'PASS':'FAIL')." — {$message}\n";if(!$condition)$fails[]=$message;};
$read=fn(string $file)=>(string)file_get_contents($root.'/'.$file);
$version=trim($read('VERSION'));
$sw=$read('public_html/service-worker.js');
$app=$read('app/App.php');
$auth=$read('app/Core/Auth.php');
$mobile=$read('app/Services/MobileAuth.php');
$android=$read('mobile-android/app/src/main/java/in/digiti/epdi/MainActivity.java');
$appGradle=$read('mobile-android/app/build.gradle');
$rootGradle=$read('mobile-android/build.gradle');
$workflow=$read('.github/workflows/build-android-apk.yml');
$scheduler=$read('bin/scheduler.php');
$manifest=json_decode($read('manifest.json'),true);

$ok($version==='2.0.1.6','VERSION is 2.0.1.6');
$ok(str_contains($sw,"const VERSION='epdi-v2.0.1.6';"),'Service Worker cache namespace matches release');
$ok(is_array($manifest)&&($manifest['version']??'')==='2.0.1.6','release manifest matches version');
$ok(str_contains($auth,'$source===\'LOCAL_MOBILE\'')&&str_contains($auth,'account_status')&&str_contains($auth,'subscriber_status')&&str_contains($auth,'identity_status'),'local mobile session validates account/subscriber/identity state');
$ok(str_contains($auth,'if($source===\'LOCAL_MOBILE\'){self::logout();return false;}')&&str_contains($auth,'catch(\\Throwable)'),'local mobile validation fails closed on store errors');
$ok(str_contains($app,'if(Auth::user())Auth::sessionAccessActive();'),'session access is revalidated after tenant resolution');
$ok(str_contains($mobile,'MobileAuthPendingException')&&str_contains($mobile,'60519'),'SNA provider pending state is distinguished');
$ok(str_contains($app,"'code'=>'MOBILE_AUTH_PENDING'")&&str_contains($app,"'retry_after_ms'=>900"),'SNA pending response exposes bounded retry signal');
$ok(str_contains($android,'maxChecks = silent ? 4 : 1')&&str_contains($android,'MOBILE_AUTH_PENDING')&&str_contains($android,'Thread.sleep(retryMs)'),'Android applies bounded SNA retry/backoff');
$ok(str_contains($android,'Use WhatsApp instead')&&str_contains($android,'Send SMS instead'),'Android provides OTP fallback actions');
$ok(str_contains($mobile,'looksCountryPrefixed')&&str_contains($mobile,"\$raw = '+' . (\$looksCountryPrefixed ? \$digits : \$cc . \$digits);"),'phone normalization avoids duplicate default country code');
$ok(str_contains($mobile,'pruneChallenges')&&str_contains($scheduler,'MobileAuth::pruneChallenges'),'challenge expiry/pruning is wired to scheduler');
$ok(str_contains($mobile,'ErrorCenter::record')&&!str_contains($mobile,"throw new \\RuntimeException(\$message !== '' ? \$message"),'provider internals are recorded rather than returned raw');
$ok(str_contains($appGradle,'compileSdk 36')&&str_contains($appGradle,'targetSdk 36'),'Android targets API 36');
$ok(str_contains($rootGradle,"version '8.13.2'")&&str_contains($workflow,"gradle-version: '8.13'"),'Android build stack is AGP 8.13.2 / Gradle 8.13');
$ok(str_contains($workflow,'bundleRelease')&&str_contains($workflow,'EPDI_UPLOAD_KEYSTORE_B64'),'CI builds release AAB with optional upload-key signing');

if($fails){fwrite(STDERR,"\nMOBILE AUTH HARDENING FAILED\n - ".implode("\n - ",$fails)."\n");exit(1);}echo"\nPASS v2.0.1.6 mobile-auth hardening guard\n";
