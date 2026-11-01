<?php
declare(strict_types=1);

$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    if(!str_starts_with($class,'Digiti\\'))return;
    $path=$root.'/app/'.str_replace('\\','/',substr($class,7)).'.php';
    if(is_file($path))require_once $path;
});
use Digiti\Admin\PublisherLoginCheck;

function verify(bool $ok,string $label):void{
    if(!$ok){fwrite(STDERR,"FAIL: ".$label."\n");exit(1);}
    echo "PASS: $label\n";
}
$domain=[
    'domain_status'=>'ROUTING_VALID','application_status'=>'ACTIVE',
    'oauth_client_id'=>'test-app-public-client','product_status'=>'ACTIVE',
    'tenant_status'=>'ACTIVE','organisation_status'=>'ACTIVE',
];
$user=['status'=>'ACTIVE'];
$membership=['status'=>'ACTIVE','role_key'=>'ADMIN'];
$pass=PublisherLoginCheck::assess($domain,$user,$membership);
verify(count(array_filter($pass))===count($pass),'Active publisher domain, user, and membership pass');
$guest=PublisherLoginCheck::assess($domain,null,null);
verify($guest['domain'] && !$guest['user'] && !$guest['membership'],'A healthy domain does not imply publisher user access');
$wrongRole=PublisherLoginCheck::assess($domain,$user,['status'=>'ACTIVE','role_key'=>'VIEWER']);
verify($wrongRole['membership'] && !$wrongRole['publisher_role'],'VIEWER cannot administer ePaper');
$inactive=PublisherLoginCheck::assess([...$domain,'tenant_status'=>'SUSPENDED'],$user,$membership);
verify(!$inactive['tenant'],'Suspended tenant fails closed');
$src=file_get_contents($root.'/app/Admin/PublisherLoginCheck.php');
$app=file_get_contents($root.'/app/App.php');
$view=file_get_contents($root.'/app/Core/View.php');
verify(str_contains($src,"if(\$role!=='SUPER_ADMIN')"),'Diagnostics restricted to Platform SUPER_ADMIN');
verify(str_contains($app,"if(\$method==='POST')Http::verifyCsrf();"),'Global admin POST handlers verify CSRF');
verify(str_contains($app,"PublisherLoginCheck::grant(\$method);"),'Scope mutation is a separate privileged POST');
verify(str_contains($src,"name=\"confirm_scope\" value=\"1\" required"),'Scope action requires affirmative confirmation');
verify(str_contains($src,'WHERE service_account_guid=? AND product_guid=? FOR UPDATE'),
    'Grant locks exact product-scoped service account');
verify(str_contains($src,'status="ACTIVE"')&&str_contains($src,'auth.direct'),
    'Only active named accounts may receive exact auth.direct scope');
verify(str_contains($src,'Audit::log('),'Scope grant is audited');
verify(str_contains($src,'organisation_guid=?'),'Membership resolution scoped to current hostname organisation');
verify(str_contains($src,'JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid'),
    'Domain lookup validates registered tenant joins');
verify(!str_contains($src,'UPDATE memberships')&&!str_contains($src,'UPDATE domains'),
    'No automatic publisher memberships or domain reassignments');
verify(str_contains($view,"'/admin/publisher-login-check','Publisher sign-in diagnostics'"),
    'Admin navigation exposes diagnostics');
echo "PASS Platform publisher sign-in audit UI contracts\n";
