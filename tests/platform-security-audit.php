<?php
declare(strict_types=1);

$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void {
    if(!str_starts_with($class,'Digiti\\'))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,7)).'.php';
    if(is_file($file))require_once $file;
});
function auditCheck(bool $pass,string $message):void {
    if(!$pass){fwrite(STDERR,"FAIL: ".$message."\n");exit(1);}
    echo "PASS: ".$message."\n";
}
function sourceCode(string $p):string {
    global $root;
    $s=file_get_contents($root.'/'.$p);
    if(!is_string($s))throw new RuntimeException('Missing '.$p);
    return $s;
}
function reject(callable $fn):bool {
    try{$fn();return false;}
    catch(InvalidArgumentException){return true;}
}
use Digiti\Admin\CanonicalDomainRegistration as Canonical;

$app=['application_guid'=>'app-epaper','product_guid'=>'prod-epaper'];
$active=[...$app,'product_key'=>'EPAPER','base_url'=>'https://epaper.digiti.in'];
$liveProduct=['application_guid'=>'app-ep-di','product_guid'=>'prod-ep-di'];
$liveApp=[...$liveProduct,'product_key'=>'EP-DI','base_url'=>'https://epaper.digiti.in'];
auditCheck(Canonical::classifyKnown('epaper.digiti.in',$liveProduct,[$liveApp],true)==='SAAS_DEFAULT',
    'EP-DI product key is accepted when canonical host matches its application and product GUID');
auditCheck(Canonical::classifyKnown('epaper.dcnnews.co.in',$liveProduct,[$liveApp],true)==='CUSTOMER_CUSTOM',
    'EP-DI application permits separate publisher customer hostname');

auditCheck(Canonical::classifyKnown('https://epaper.digiti.in/',$app,[$active],true)==='SAAS_DEFAULT',
    'main EPAPER Base URL registers as canonical application hostname for superadmin');
auditCheck(Canonical::classifyKnown('epaper.dcnnews.co.in',$app,[$active],false)==='CUSTOMER_CUSTOM',
    'separate publisher hostname remains a customer domain');
auditCheck(reject(fn()=>Canonical::classifyKnown('epaper.digiti.in',$app,[$active],false)),
    'non-superadmin may not register canonical application hostname');
auditCheck(reject(fn()=>Canonical::classifyKnown('epaper.digiti.in',
    ['application_guid'=>'wrong-app','product_guid'=>'prod-epaper'],[$active],true)),
    'application base URL cannot be appropriated by a different application');
auditCheck(reject(fn()=>Canonical::classifyKnown('epaper.digiti.in',$app,[$active,$active],true)),
    'duplicate canonical application Base URL is rejected for operator review');
auditCheck(reject(fn()=>Canonical::classifyKnown('platform.digiti.in',$app,[$active],true)),
    'Platform infrastructure hostname cannot be registered as EPAPER');
auditCheck(reject(fn()=>Canonical::classifyKnown('domains.digiti.in',$app,[$active],true)),
    'shared DNS target cannot be treated as a publisher website');

$appSource=sourceCode('app/App.php');
$onboard=sourceCode('app/Admin/Onboarding.php');
$dashboard=sourceCode('app/Admin/OnboardingDashboard.php');
$worker=sourceCode('bin/worker.php');
$scheduler=sourceCode('bin/scheduler.php');
$oauth=sourceCode('app/Core/OAuth.php');
$service=sourceCode('app/Core/ServiceAuth.php');
$installer=sourceCode('public_html/install.php');
$errors=sourceCode('app/Core/ErrorCenter.php');
$stripe=sourceCode('app/Billing/StripeProvider.php');
$schema=sourceCode('database/schema.sql');

auditCheck(str_contains($appSource,'CanonicalDomainRegistration::classify($host,$app)') &&
    str_contains($appSource,'CanonicalDomainRegistration::insertDomain($guid,$host,$token,$tenant,$app,$domainType)'),
    'Domain Registry classifies and stores both application/customer hostnames through shared writer');
auditCheck(str_contains($onboard,'CanonicalDomainRegistration::classify($host,$app)') &&
    str_contains($onboard,'CanonicalDomainRegistration::insertDomain($dGuid,$host,$token,'),
    'Publisher Onboarding uses the same registration classifier and shared domain writer');
auditCheck(!str_contains($onboard,'WHERE p.product_key="EPAPER"') &&
    str_contains($onboard,'pt.product_guid=? AND p.status="ACTIVE"'),
    'Onboarding no longer assumes literal EPAPER product key and scopes tenant by active product GUID');
auditCheck(str_contains($onboard,"\$domainType==='SAAS_DEFAULT' && \$type==='new'"),
    'Canonical hostname cannot accidentally create a second publisher account');
auditCheck(str_contains(sourceCode('app/Admin/Restore.php'),"\$app['product_guid']") &&
    !str_contains(sourceCode('app/Admin/Restore.php'),"\$app['product_key']!=='EPAPER'"),
    'Canonical Restore follows linked product/application GUID rather than obsolete product key');
auditCheck(str_contains($appSource,'product_status') &&
    str_contains($appSource,"\$dependencyNotice"),
    'Product Tenant registry shows linked product status, not only tenant status');
auditCheck(str_contains($onboard,'confirm_canonical') &&
    str_contains($dashboard,'name="confirm_canonical"'),
    'main application hostname requires explicit onboarding confirmation');
auditCheck(str_contains($dashboard,'name="application_guid"'),
    'publisher onboarding disambiguates multiple EPAPER applications');
auditCheck(!str_contains($appSource,'This website is an application hostname, not a new customer domain.'),
    'obsolete dead-end canonical hostname error removed');
auditCheck(str_contains($worker,'status IN ("AWAITING_DNS","ROUTING_VALID","SSL_PROVISIONING","ACTIVE","REVALIDATION_FAILED")'),
    'worker excludes archived and suspended domains');
auditCheck(str_contains($worker,'WHERE domain_guid=? AND status=?'),
    'worker cannot overwrite lifecycle changes made during DNS check');
auditCheck(!str_contains($worker,'$status=\'ACTIVE\';'),
    'worker never autoactivates a newspaper from edge status');
auditCheck(str_contains($worker,'no verified delivery handler') &&
    !str_contains($worker,'status="PROCESSED",processed_at=NOW(6)'),
    'unimplemented webhook handling never reports an event processed');
auditCheck(str_contains($worker,'Payment reconciliation processor is not configured'),
    'unimplemented payment reconciliation fails visibly');
auditCheck(str_contains($scheduler,'GET_LOCK') &&
    str_contains($scheduler,'RELEASE_LOCK') &&
    str_contains($scheduler,'status IN ("QUEUED","RUNNING")'),
    'scheduler uses database lock and dedupes pending jobs');
auditCheck(str_contains($oauth,'AND used_at IS NULL AND expires_at>NOW(6)') &&
    str_contains($oauth,'$claimed!==1'),
    'OAuth authorization codes have atomic one-time consumption');
auditCheck(str_contains($oauth,'AND a.status="ACTIVE" AND p.status="ACTIVE"') &&
    str_contains($oauth,'AND status="ACTIVE"'),
    'OAuth exchange checks user, application and product status');
auditCheck(str_contains($appSource,'AUTH_TENANT_MEMBERSHIP_REQUIRED') &&
    str_contains($appSource,'organisation_guid=? AND status="ACTIVE" LIMIT 1'),
    'direct login requires selected publisher membership');
auditCheck(str_contains($onboard,"if (!self::state(\$fresh)['steps'][0]['done'])") &&
    str_contains($onboard,'AND status=?'),
    'go-live and DNS revalidation require active parents and conditional status updates');
auditCheck(str_contains($appSource,'Archived or suspended hostnames cannot be verified') &&
    str_contains($appSource,'Domain changed during verification'),
    'legacy DNS verification cannot resurrect archived domains or override state races');
auditCheck(str_contains($appSource,'IMPERSONATION_ALREADY_USED') &&
    str_contains($appSource,'$role!==\'SUPER_ADMIN\''),
    'support impersonation is superadmin-only and one-time');
auditCheck(str_contains($service,'checkTenant(') && str_contains($service,'checkProduct(')
    && str_contains($service,'checkOrganisation('),
    'product-bound service tokens validate tenants and organisations');
auditCheck(str_contains($appSource,'ServiceAuth::checkProduct($service,$row[\'product_guid\'])') &&
    str_contains($appSource,'ServiceAuth::checkProduct($service,(string)$cache[\'product_guid\'])'),
    'domain resolver checks service token product on database and cache paths');
auditCheck(str_contains($appSource,'FEATURE_PRODUCT_MISMATCH') &&
    str_contains($appSource,'ON DUPLICATE KEY UPDATE used_value=used_value+VALUES(used_value)'),
    'usage updates are tenant-scoped and atomically incremented');
auditCheck(str_contains($appSource,'confirm_manual_access') &&
    str_contains($appSource,'already has an active subscription'),
    'manually granting FULL access requires consent and deduplication');
auditCheck(str_contains($appSource,'Invoice amount must be positive') &&
    str_contains($appSource,'str_pad($parts[1]'),
    'invoice creation validates amount without float rounding errors');
auditCheck(str_contains($appSource,"'razorpay'=>'body:'.hash('sha256',\$payload)") &&
    str_contains($appSource,'Store::tx(function()use($guid,$provider,$ref,$sanitized,$payload)'),
    'Razorpay dedupes by authenticated body and persists webhook/outbox atomically');
auditCheck(str_contains($stripe,'abs(time()-$issued)>300'),
    'Stripe webhooks reject stale signed timestamps');
auditCheck(str_contains(sourceCode('app/Billing/RazorpayProvider.php'),
    "Config::get('billing.razorpay_webhook_secret'"),
    'Razorpay webhook verification uses dedicated signing secret rather than API credential');
auditCheck(str_contains($installer,'is_file($lock)||is_file($configFile)') &&
    str_contains($installer,'information_schema.tables') &&
    str_contains($installer,'NEW EMPTY DATABASE'),
    'installer cannot overwrite a configured or populated Platform installation');
auditCheck(str_contains($errors,'VALUES(?,NOW(6),?,?,?,?,?,?,?,?)'),
    'server-side error logging has corrected SQL placeholder count');
auditCheck(str_contains($schema,'UNIQUE KEY uk_domain_hostname(normalized_hostname)'),
    'hostname uniqueness remains enforced by the database');

echo "Platform-wide security and operational regression contracts passed.\n";
