<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Services/PlatformBinding.php';

use EpaperSaas\Services\PlatformBinding;

function check(bool $ok,string $title):void {
    if(!$ok){fwrite(STDERR,"FAIL: $title\n");exit(1);}
    echo "PASS: $title\n";
}
$expected=[
    'canonical_url'=>'https://epaper.digiti.in',
    'application_guid'=>'01a19000-0000-7000-8000-000000000002',
    'product_guid'=>'01a19000-0000-7000-8000-000000000003',
    'default_tenant_guid'=>'01a19000-0000-7000-8000-000000000004',
    'allow_canonical_fallback'=>false,
];
$record=[
    'hostname'=>'epaper.digiti.in',
    'base_url'=>'https://epaper.digiti.in',
    'product_key'=>'EP-DI',
    'application_key'=>'EPDI_APP',
    'application_guid'=>$expected['application_guid'],
    'product_guid'=>$expected['product_guid'],
    'product_tenant_guid'=>$expected['default_tenant_guid'],
    'status'=>'ACTIVE'
];
check(PlatformBinding::check($record,'epaper.digiti.in',$expected)['ok'],
    'EP-DI live canonical domain bound to matching product and application GUID');
check(PlatformBinding::check([...$record,'product_key'=>'EPAPER'],
    'epaper.digiti.in',$expected)['ok'],
    'product display key does not authorize or deny a valid app binding');
check(PlatformBinding::check([...$record,'hostname'=>'epaper.dcnnews.co.in'],
    'epaper.dcnnews.co.in',$expected)['ok'],
    'DCN customer alias may share the EP-DI application without sharing a tenant');
check(PlatformBinding::check([...$record,'application_guid'=>'01a19000-0000-7000-8000-000000000999'],
    'epaper.digiti.in',$expected)['code']==='CONFIGURED_APPLICATION_GUID_STALE',
    'different application GUID fails closed rather than selecting a different publisher');
check(PlatformBinding::check([...$record,'product_guid'=>'01a19000-0000-7000-8000-000000000999'],
    'epaper.digiti.in',$expected)['code']==='CONFIGURED_PRODUCT_GUID_STALE',
    'different product GUID fails closed');
check(PlatformBinding::check([...$record,'base_url'=>'https://another-product.example'],
    'epaper.digiti.in',$expected)['code']==='APPLICATION_BASE_URL_MISMATCH',
    'app under another canonical base URL is denied');
check(PlatformBinding::check([...$record,'status'=>'REMOVED'],
    'epaper.digiti.in',$expected)['code']==='DOMAIN_NOT_ACTIVE',
    'removed domain is not permitted to serve reader traffic');
check(PlatformBinding::check([...$record,'hostname'=>'other.digiti.in'],
    'epaper.digiti.in',$expected)['code']==='HOSTNAME_MISMATCH',
    'domain resolver cannot return another hostname');
check(!PlatformBinding::check([...$record,'status'=>'CANONICAL_FALLBACK'],
    'epaper.digiti.in',$expected)['ok'],
    'legacy default tenant fallback is disabled without explicit emergency opt-in');
$emergency=[...$record,'status'=>'CANONICAL_FALLBACK'];
check(PlatformBinding::check($emergency,'epaper.digiti.in',
    [...$expected,'allow_canonical_fallback'=>true])['ok'],
    'emergency fallback is available only for fully pinned GUIDs');
check(!PlatformBinding::check($emergency,'epaper.digiti.in',
    [...$expected,'allow_canonical_fallback'=>true,'product_guid'=>''])['ok'],
    'fallback never accepts missing product GUID pin');

$resolver=file_get_contents(dirname(__DIR__).'/app/Services/DomainResolver.php');
$app=file_get_contents(dirname(__DIR__).'/app/App.php');
$config=file_get_contents(dirname(__DIR__).'/config/config.example.php');
check(str_contains($resolver,'domain:binding-v3:'),
    'new domain cache version skips stale EPAPER redis/file records');
check(!str_contains($resolver,"if((\$d['product_key']??'')!=='EPAPER')"),
    'hard-coded EPAPER rejection is removed from reader request path');
check(str_contains($resolver,'PlatformBinding::check($domain,$host,self::expectedBinding())'),
    'fresh remote domain must pass application/product identity validation');
check(str_contains($resolver,'PlatformBinding::check($cache,$host,self::expectedBinding())'),
    'cached domain must pass same identity validation');
check(str_contains($resolver,"Config::get('platform.allow_canonical_fallback',false)"),
    'canonical fallback is explicitly opt-in');
check(str_contains($app,'http_response_code(503);')
    &&str_contains($app,'Platform publisher binding')
    &&str_contains($app,'<main class="card">'),
    'pre-tenant setup error is safe and readiness is binding-aware');
check(str_contains($config,"'product_guid'=>''"),
    'example private integration config supports optional explicit product GUID');
check(str_contains($config,"'allow_canonical_fallback'=>false"),
    'example config disables unsafe legacy fallback by default');

echo "EP-DI Platform integration binding contracts passed.\n";
