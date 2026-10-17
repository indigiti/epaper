<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/TrackedFilePolicy.php';
use EpaperSaas\Core\TrackedFilePolicy;
$cases=[
 ['codebase/config/config.php',true],
 ['codebase/config/runtime.php',true],
 ['codebase/config/config.example.php',false],
 ['codebase/config/install.token',true],
 ['codebase/storage/installed.lock',true],
 ['codebase/public_html/apply-login-580px-hotfix.php',true],
 ['codebase/secrets/.env',true],
 ['codebase/secrets/.env.production',true],
 ['codebase/secrets/private.key',true],
 ['codebase/secrets/server.pem',true],
 ['codebase/secrets/certificate.pfx',true],
 ['codebase/secrets/firebase-service-account.json',true],
 ['codebase/secrets/service-account-prod.json',true],
 ['codebase/app/Core/Config.php',false],
 ['codebase/tests/installer-guard.php',false],
 ['codebase/public_html/assets/reader.js',false],
 ['codebase/storage/media/page.webp',false],
 ['codebase/release/RELEASE-CHECK-2.0.2.1.txt',false],
];
$fails=0;
foreach($cases as [$path,$expected]){
    if(TrackedFilePolicy::prohibited($path)!==$expected){fwrite(STDERR,"FAIL tracked file policy: $path\n");$fails++;}
}
if($fails)exit(1);
echo "PASS tracked file policy (".count($cases)." cases)\n";
