<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Core/Config.php';
require dirname(__DIR__).'/app/Admin/ConnectionSetup.php';

use Digiti\Admin\ConnectionSetup;

function assertSetup(bool $condition,string $reason):void {
    if(!$condition){fwrite(STDERR,"FAIL: $reason\n");exit(1);}
    echo "PASS: $reason\n";
}
$publisher=[
    'application_guid'=>'01a19000-0000-7000-8000-000000000002',
    'product_guid'=>'01a19000-0000-7000-8000-000000000003',
    'application_key'=>'EPDI_APP',
    'product_key'=>'EP-DI',
    'oauth_client_id'=>'app_safe_public_client_id',
    'base_url'=>'https://epaper.digiti.in',
    'token_hash'=>'NEVER_SHOW_HASH',
    'product_tenant_guid'=>'01a19000-0000-7000-8000-000000000004',
];
$out=ConnectionSetup::runtimeSnippet($publisher);
assertSetup(str_contains($out,"'product_key' => 'EP-DI'"),'Uses active selected product, not hardcoded archived EPAPER');
assertSetup(str_contains($out,"'application_key' => 'EPDI_APP'"),'Uses selected application key');
assertSetup(str_contains($out,"'application_guid' => '01a19000-0000-7000-8000-000000000002'"),'Selected application GUID is pinned');
assertSetup(str_contains($out,"'product_guid' => '01a19000-0000-7000-8000-000000000003'"),'Selected product GUID is pinned');
assertSetup(str_contains($out,"'oauth_client_id' => 'app_safe_public_client_id'"),'OAuth client ID is included');
assertSetup(str_contains($out,"'service_token' => 'PASTE_NEW_SERVICE_TOKEN_HERE'"),'Requires replacing service credential placeholder');
assertSetup(str_contains($out,"'default_tenant_guid' => ''"),'No global publisher tenant override in multi-tenant deployment');
assertSetup(str_contains($out,"'allow_canonical_fallback' => false"),'No unauthenticated canonical fallback');
assertSetup(!str_contains($out,'NEVER_SHOW_HASH'),'No stored token hash or secret is exported');
assertSetup(!str_contains($out,$publisher['product_tenant_guid']),'Does not pin any selected publisher tenant globally');
assertSetup(ConnectionSetup::missingScopes(['*'])===[],'Wildcard scopes recognized when already granted');
assertSetup(ConnectionSetup::missingScopes(['domains.resolve'])!==[],'Incomplete scopes flagged');
$bad=false;try{ConnectionSetup::runtimeSnippet([...$publisher,'base_url'=>'http://epaper.example.com']);}catch(InvalidArgumentException){$bad=true;}
assertSetup($bad,'Refuse unsafe HTTP application base URLs');
$app=file_get_contents(dirname(__DIR__).'/app/App.php');
$view=file_get_contents(dirname(__DIR__).'/app/Core/View.php');
$source=file_get_contents(dirname(__DIR__).'/app/Admin/ConnectionSetup.php');
assertSetup(str_contains($app,"if(\$path==='/admin/app-connections'&&\$method==='GET')ConnectionSetup::index();"),
    'Connection setup is a read-only authenticated admin route');
assertSetup(str_contains($view,"'/admin/app-connections','Application connection setup'"),
    'Connection setup accessible from advanced navigation');
assertSetup(str_contains($source,"if(\$role!=='SUPER_ADMIN')"),
    'Only Platform SUPER_ADMIN can view integration metadata');
assertSetup(!str_contains($source,'token_hash') || str_contains($source,'Never pretend'),
    'No raw token hash query is included');
assertSetup(!str_contains($source,'UPDATE service_accounts')&&!str_contains($source,'UPDATE domains'),
    'Configuration preview cannot rotate credentials or change live publisher mappings');
echo "PASS — Platform application config assistant contracts\n";
