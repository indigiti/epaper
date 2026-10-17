<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$ok=function(bool$c,string$m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};$read=fn(string$f)=>(string)@file_get_contents($root.'/'.$f);
$version=trim($read('VERSION'));$sw=$read('public_html/service-worker.js');$app=$read('app/App.php');$auth=$read('app/Core/Auth.php');$desktop=$read('app/Services/DesktopAuth.php');$trusted=$read('app/Services/TrustedAppAuth.php');$sessions=$read('app/Services/ReaderSession.php');$schema=$read('database/schema.sql');$android=$read('mobile-android/app/build.gradle');$main=$read('mobile-android/app/src/main/java/in/digiti/epdi/MainActivity.java');$manifest=json_decode($read('manifest.json'),true);
$ok(version_compare($version,'2.0.1.8','>='),'VERSION remains at or above v2.0.1.8');
$ok(str_contains($sw,"const VERSION='epdi-v{$version}'"),'Service Worker aligned');
$ok(is_file($root.'/BUILD-MANIFEST-2.0.1.8.md')&&is_file($root.'/UPDATE-2.0.1.8.md'),'v2.0.1.8 release documentation packaged');
$ok(is_file($root.'/database/upgrade-v2.0.1.8-trusted-app-desktop-login.sql'),'trusted app / desktop migration packaged');
$ok(str_contains($app,"/api/v1/auth/desktop/status")&&str_contains($app,"/api/v1/auth/trusted/decision"),'desktop/trusted APIs routed');
$ok(str_contains($desktop,'ep_desktop_login_challenges')&&str_contains($desktop,"'APPROVED'")&&str_contains($desktop,'display_code'),'desktop challenge lifecycle packaged');
$ok(str_contains($trusted,'ep_reader_devices')&&str_contains($trusted,"EPDI-Android/")&&str_contains($trusted,'credential_hash'),'trusted app registry packaged and Android-scoped');
$ok(str_contains($sessions,'ep_reader_sessions')&&str_contains($sessions,'MOBILE_APP')&&str_contains($sessions,'DESKTOP_WEB')&&str_contains($sessions,'REPLACED'),'one-session-per-kind enforcement packaged');
$ok(str_contains($schema,'CREATE TABLE IF NOT EXISTS ep_reader_devices')&&str_contains($schema,'CREATE TABLE IF NOT EXISTS ep_reader_sessions')&&str_contains($schema,'CREATE TABLE IF NOT EXISTS ep_desktop_login_challenges'),'new schema tables packaged');
$ok(str_contains($android,'compileSdk 36')&&str_contains($android,'targetSdk 36')&&str_contains($android,"versionName '2.0.2.0'")&&str_contains($android,'firebase-bom:34.19.0'),'Android API/version/FCM dependency aligned');
$ok(str_contains($main,'Desktop login request')&&str_contains($main,'/api/v1/auth/trusted/decision')&&str_contains($main,'EPDI-Android/2.0.2.0'),'Android approve/deny client packaged');
$ok(is_array($manifest)&&($manifest['version']??'')===$version,'manifest.json aligned');
if($fails){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$fails)."\n");exit(1);}echo"\nPASS v2.0.1.8 guard\n";
