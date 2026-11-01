<?php
declare(strict_types=1);

$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    if(!str_starts_with($class,'Digiti\\'))return;
    $path=$root.'/app/'.str_replace('\\','/',substr($class,7)).'.php';
    if(is_file($path))require_once $path;
});
function ok(bool $condition,string $message):void {
    if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}
function source(string $path):string{
    global $root;
    $text=file_get_contents($root.'/'.$path);
    if(!is_string($text))throw new RuntimeException('Missing source '.$path);
    return $text;
}
function methodSource(string $source,string $method):string{
    $a=strpos($source,'function '.$method.'(');
    if($a===false)throw new RuntimeException('Missing method '.$method);
    $b=strpos($source,'private static function ',$a+14);
    if($b===false)$b=strlen($source);
    return substr($source,$a,$b-$a);
}

use Digiti\Core\DomainTools;
use Digiti\Core\ErrorCenter;
use Digiti\Admin\ActionUi;
use Digiti\Admin\Restore;

ok(DomainTools::publicHostname('epaper.dcnnews.co.in')==='epaper.dcnnews.co.in','bare publisher hostname accepted');
ok(DomainTools::publicHostname('https://EPAPER.DCNNEWS.CO.IN/')==='epaper.dcnnews.co.in','pasted HTTPS publisher URL normalized');
ok(DomainTools::publicHostname('epaper.dcnnews.co.in.')==='epaper.dcnnews.co.in','trailing DNS dot normalized');
foreach(['https://example.com/private','https://admin@example.com','https://example.com:8443','127.0.0.1','localhost','internal.local','https://example.com/?page=1'] as $bad){
    $rejected=false;
    try{DomainTools::publicHostname($bad);}catch(InvalidArgumentException){$rejected=true;}
    ok($rejected,'reject invalid/non-public URL: '.$bad);
}

$guid='01a192e0-b76e-7e83-9544-ec69eb7fb8be';
$restoreUrl='/admin/domains/'.$guid.'/restore';
$editUrl='/admin/domains/'.$guid.'/edit';
ok(str_contains(ActionUi::row('domain',$guid,'REMOVED','/admin/domains'),'href="'.$restoreUrl.'"'),'archived domain links to GET confirmation');
ok(!str_contains(ActionUi::row('domain',$guid,'REMOVED','/admin/domains'),'<form'),'restore does not execute implicitly');
ok(str_contains(ActionUi::row('domain',$guid,'ACTIVE','/admin/domains'),'Edit domain'),'active domain retains edit option');
ok(ErrorCenter::returnPath('/admin/domains/'.$guid.'/mapping')===$editUrl,'failed tenant mapping returns to edit screen');
ok(ErrorCenter::returnPath('/admin/domains/'.$guid.'/restore')===$restoreUrl,'failed recovery returns to restore preview');
ok(str_starts_with(ErrorCenter::returnPath('/admin/onboarding/'.$guid.'/verify'),'/admin/onboarding?domain='),'failed DNS check returns to tracker');
ok(ErrorCenter::returnPath('/admin/domains/'.$guid.'/remove')==='/admin/domains','failed archive returns to domain registry');
ok(ErrorCenter::returnPath('/admin/invalid')==='/admin','invalid path safely redirects to dashboard');

$label=(new ReflectionMethod(Restore::class,'recordLabel'));
ok($label->invoke(null,'domains',['hostname'=>'epaper.dcnnews.co.in'])==='epaper.dcnnews.co.in','archived domain restore requires exact hostname');
ok($label->invoke(null,'users',['email'=>'operator@example.com'])==='operator@example.com','archived user restore requires email');

$restore=source('app/Admin/Restore.php');
$onboarding=source('app/Admin/Onboarding.php');
$mapping=source('app/Admin/DomainMapping.php');
$app=source('app/App.php');
$crud=source('app/Admin/Crud.php');
$schema=source('database/schema.sql');
$ui=source('app/Admin/OnboardingDashboard.php');

ok(str_contains($restore,"if(\$method==='GET')")&&str_contains($restore,"View::layout('Restore archived record'"),'GET recovery shows an explicit review');
ok(str_contains($restore,'confirm_record')&&str_contains($restore,'confirm_restore'),'POST recovery requires typed record identity and acknowledgement');
ok(str_contains($restore,"'/admin/domains/'.rawurlencode(\$guid).'/edit'"),'successful domain recovery opens same record for correction');
ok(str_contains($restore,'checkDependencies($type,$record)'),'restore checks parent entities');
ok(str_contains($restore,"Uuid::toBinary(\$foreign)"),'restore compares binary UUID foreign keys');
ok(str_contains($restore,'registry.restore'),'restore audit preserved');
ok(str_contains($onboarding,"'/admin/domains/'.rawurlencode(\$guid).'/restore'"),'duplicate archived hostname from onboarding goes to recovery');
ok(str_contains($app,"'/admin/domains/'.rawurlencode(\$existingGuid).'/restore'"),'duplicate archived hostname from domain registry goes to recovery');
ok(str_contains($ui,'archivedTable($archived)'),'archived publisher inventory visible');
ok(str_contains($app,'DomainTools::publicHostname((string)'),'domain registry normalizes pasted URLs');
ok(str_contains($onboarding,'DomainTools::publicHostname((string)'),'onboarding normalizes pasted URLs');
ok(str_contains($app,'AND a.product_guid=d.product_guid')&&
    str_contains($app,'AND pt.product_guid=d.product_guid AND pt.organisation_guid=d.organisation_guid'),'API rejects product and organisation mismatches');
ok(str_contains($onboarding,'tenant_product_guid')&&str_contains($onboarding,'tenant_organisation_guid'),'onboarding rejects mismatched tenant ownership');
ok(str_contains($mapping,'Current newspaper unavailable — select a publisher'),'missing current tenant cannot silently select first alternative');
ok(str_contains($mapping,'Current application unavailable — select a compatible application'),'missing current application cannot silently select first alternative');
ok(str_contains($schema,'UNIQUE KEY uk_domain_hostname(normalized_hostname)'),'domain hostname uniqueness preserved');
ok(str_contains($schema,'fk_domain_tenant')&&str_contains($schema,'fk_domain_app'),'tenant and application foreign keys preserved');
foreach(['organisationRemove','userRemove','productRemove','applicationRemove','tenantRemove','planRemove'] as $name)
    ok(str_contains(methodSource($crud,$name),'Store::tx('),'transactional archive: '.$name);
ok(str_contains(methodSource($crud,'serviceAccountRotate'),'ensureNotRemoved('),'archived service account cannot be rotated directly');
ok(str_contains(methodSource($crud,'userEdit'),'if($new!==\'\'&&strlen($new)<10)'),'user edit validates password length before mutation');
ok(str_contains(methodSource($crud,'userEdit'),'Store::tx('),'user edits are atomic');
ok(str_contains(source('app/Admin/StagingReset.php'),"Config::get('app.allow_staging_reset',false)===true"),'staging reset remains opt-in and isolated');

echo "Platform recovery source and runtime audit checks passed.\n";
