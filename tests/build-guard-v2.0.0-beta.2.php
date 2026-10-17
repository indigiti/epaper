<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$pass=function(bool$ok,string$name)use(&$fails){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fails[]=$name;};$read=fn(string$f):string=>is_file($root.'/'.$f)?(string)file_get_contents($root.'/'.$f):'';
$version=trim($read('VERSION'));$app=$read('app/App.php');$view=$read('app/Core/View.php');$vault=$read('app/Services/OfflineVault.php');$doctor=$read('app/Services/SchemaDoctor.php');$sw=$read('public_html/service-worker.js');$manifest=$read('public_html/manifest.webmanifest');$accountCss=$read('public_html/assets/account-beta.css');$vaultJs=$read('public_html/assets/offline-vault.js');
$pass($version==='2.0.0-beta.2','VERSION locked to 2.0.0-beta.2');
$pass(str_contains($app,'private function accountLibrary')&&str_contains($app,'private function accountDevices'),'Reader OS Library and Devices implemented');
$pass(str_contains($view,"'/account/library'")&&str_contains($view,"'/account/devices'"),'Reader OS navigation includes Library and Devices');
$pass(str_contains($app,'apiOfflineLicense')&&str_contains($app,'apiOfflinePage')&&str_contains($app,'apiOfflineComplete'),'encrypted Offline Vault APIs implemented');
$pass(str_contains($vault,'ECDH-P256+HKDF-SHA256+AES-256-GCM')&&str_contains($vault,'sealCek'),'device key wrapping and server CEK sealing implemented');
$pass(str_contains($doctor,'BETA2_TABLES')&&str_contains($doctor,'BETA2_ITEM_COLUMNS')&&str_contains($doctor,'BETA2_INDEXES'),'Schema Doctor repairs beta.2 Offline Vault schema');
$pass(str_contains($sw,"const VERSION='epdi-v2.0.0-beta.2'"),'service worker cache version aligned');
$pass(str_contains($sw,"'/offline-library.html'")&&str_contains($sw,"'/offline-reader.html'"),'offline PWA shells cached');
$pass(!str_contains($sw,'epdi-v2-legacy-reader-media'),'legacy plaintext reader-media cache removed');
$pass(str_contains($manifest,'Encrypted Offline Vault')||str_contains($manifest,'encrypted offline editions'),'PWA manifest describes encrypted offline capability');
$pass(str_contains($accountCss,'.reader-os-hero')&&str_contains($accountCss,'.library-tabs'),'Reader OS and Library design system included');
$pass(str_contains($vaultJs,'downloadEdition')&&str_contains($vaultJs,'readPage')&&str_contains($vaultJs,'wipe'),'offline download/read/logout wipe client lifecycle included');
if($fails){fwrite(STDERR,"\nBUILD GUARD FAILED: ".implode(', ',$fails)."\n");exit(1);}echo"\nALL EP-DI v2.0.0-beta.2 BUILD GUARDS PASSED\n";
