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
    if(preg_match('/\\n\\s*(?:private|public|protected)(?:\\s+static)?\\s+function\\s+[A-Za-z_]\\w*\\s*\\(/',$source,$found,PREG_OFFSET_CAPTURE,$a+strlen('function '.$method)))
        $b=$found[0][1];
    else $b=strlen($source);
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

ok(str_contains($restore,'Restore ePaper application hostname'),'canonical ePaper recovery has its own guided screen');
ok(str_contains($restore,'confirm_canonical'),'canonical recovery requires extra explicit acknowledgement');

ok(str_contains($restore,'self::dependencyGuide($record)'),'domain Restore displays real parent dependency inventory');

ok(str_contains($restore,'self::freshStartAlternative($record)'),'canonical recovery shows the decline-restore choice');
ok(str_contains($restore,'Do not restore — release archived resources'),'canonical recovery now offers permanent archived cleanup');
ok(str_contains($restore,'/admin/products/')&&str_contains($restore,\x27,'archived product is directly linked to resource release');
ok(str_contains($restore,'original GUID references, invoices, payments, refunds'),'cleanup alternative explains immutable history');
ok(str_contains($restore,'/admin/domains/')&&str_contains($restore,\x27,'archived domain is independently releasable');
$optOut=methodSource($restore,'freshStartAlternative');
ok(!str_contains($optOut,'Store::exec(')&&!str_contains($optOut,'Store::tx('),
    'decline-restore page does not mutate production records');

ok(str_contains($restore,'Repair required records in this order'),'recovery page explains remediation order');
ok(str_contains($restore,"'/restore':\$url.'/edit'"),'archived parents link to Restore and suspended parents to Edit');
ok(str_contains($restore,'The linked $label is archived (REMOVED)'),'generic blocker identifies archived parent rather than a vague rejection');
ok(str_contains($restore,"in_array(\$type,['products','organisations','applications','product-tenants'],true)"),'restored parents open their Edit screen for deliberate activation');

ok(str_contains($restore,'$role!==\'SUPER_ADMIN\''),'canonical recovery restricted to Platform super administrators');
ok(str_contains($restore,'$canonical=self::canonicalDomain($record)'),'archived canonical record classified against application base URL');
ok(str_contains($restore,'tenant_product_guid')&&str_contains($restore,'application_product_guid'),'canonical recovery checks product/organisation consistency');
ok(str_contains($restore,'$parents[$name.\'_status\']!==\'ACTIVE\''),'all parent entities must be active before domain restoration');
ok(str_contains($restore,"'/admin/onboarding?domain='.rawurlencode(\$guid)"),'restored canonical hostname returns to verification, not unsafe mapping');

ok(str_contains($onboarding,"'/admin/domains/'.rawurlencode(\$guid).'/restore'"),'duplicate archived hostname from onboarding goes to recovery');
ok(str_contains($app,"'/admin/domains/'.rawurlencode(\$existingGuid).'/restore'"),'duplicate archived hostname from domain registry goes to recovery');
ok(str_contains($ui,'archivedTable($archived)'),'archived publisher inventory visible');
ok(str_contains($app,'DomainTools::publicHostname((string)'),'domain registry normalizes pasted URLs');

ok(str_contains($app,'private function reuseExisting('),'duplicate records share one safe recovery redirect');

$cleanup=source('app/Admin/RegistryCleanup.php');
$actions=source('app/Admin/ActionUi.php');
ok(str_contains($app,'RegistryCleanup::run('),'super-admin resource cleanup route wired before CRUD dispatch');
ok(str_contains($actions,"/release"),'archived rows show permanent release alongside Restore');
ok(str_contains($cleanup,'superAdmin()'),'cleanup requires super administrator permission');
ok(str_contains($cleanup,'Store::tx(')&&str_contains($cleanup,'self::audit('),'cleanup and durable audit share a transaction');
ok(str_contains($cleanup,'DELETE FROM domains WHERE product_guid=? AND status="REMOVED"'),'archived domain registrations are physically removed');
ok(str_contains($cleanup,'DELETE FROM applications WHERE product_guid=? AND status="REMOVED"'),'archived applications are physically removed');
ok(str_contains($cleanup,'UPDATE products SET product_key=?'),'archived products release unique keys while retaining billed identity');
ok(str_contains($cleanup,'subscriptions')&&str_contains($cleanup,'invoices')&&str_contains($cleanup,'payments'),
    'financial record tables participate in retention checks');
ok(!str_contains($cleanup,"DELETE FROM invoices")&&!str_contains($cleanup,"DELETE FROM payments")
    &&!str_contains($cleanup,"DELETE FROM subscriptions")&&!str_contains($cleanup,"DELETE FROM audit_events"),
    'cleanup never deletes immutable financial/audit records');
ok(str_contains($cleanup,"\$retained>0"),'retained billing lineage prevents deletion of original product GUID');

foreach(['organisations','products','applications','productTenants','users'] as $method)
    ok(str_contains(methodSource($app,$method),'$this->reuseExisting('),
        'existing '. $method .' record opens Edit or Restore rather than duplicate INSERT');

ok(str_contains($onboarding,'DomainTools::publicHostname((string)'),'onboarding normalizes pasted URLs');
ok(str_contains($app,'AND a.product_guid=d.product_guid')&&
    str_contains($app,'AND pt.product_guid=d.product_guid AND pt.organisation_guid=d.organisation_guid'),'API rejects product and organisation mismatches');
ok(str_contains($onboarding,'tenant_product_guid')&&str_contains($onboarding,'tenant_organisation_guid'),'onboarding rejects mismatched tenant ownership');
ok(str_contains($mapping,'Current newspaper unavailable — select a publisher'),'missing current tenant cannot silently select first alternative');
ok(str_contains($mapping,'Current application unavailable — select a compatible application'),'missing current application cannot silently select first alternative');
ok(str_contains($schema,'UNIQUE KEY uk_domain_hostname(normalized_hostname)'),'domain hostname uniqueness preserved');

ok(str_contains($schema,'UNIQUE KEY uk_product_tenant_org(organisation_guid,product_guid)'),'tenant uniqueness preserved by organisation/product');

ok(str_contains($schema,'fk_domain_tenant')&&str_contains($schema,'fk_domain_app'),'tenant and application foreign keys preserved');
foreach(['organisationRemove','userRemove','productRemove','applicationRemove','tenantRemove','planRemove'] as $name)
    ok(str_contains(methodSource($crud,$name),'Store::tx('),'transactional archive: '.$name);
ok(str_contains(methodSource($crud,'serviceAccountRotate'),'ensureNotRemoved('),'archived service account cannot be rotated directly');
ok(str_contains(methodSource($crud,'userEdit'),'if($new!==\'\'&&strlen($new)<10)'),'user edit validates password length before mutation');
ok(str_contains(methodSource($crud,'userEdit'),'Store::tx('),'user edits are atomic');
ok(str_contains(source('app/Admin/StagingReset.php'),"Config::get('app.allow_staging_reset',false)===true"),'staging reset remains opt-in and isolated');

echo "Platform recovery source and runtime audit checks passed.\n";
+'productGuid\x27),'archived product is directly linked to resource release');
ok(str_contains($restore,'original GUID references, invoices, payments, refunds'),'cleanup alternative explains immutable history');
ok(str_contains($restore,"'/admin/domains/'.\$domainGuid.'/release'"),'archived domain is independently releasable');
$optOut=methodSource($restore,'freshStartAlternative');
ok(!str_contains($optOut,'Store::exec(')&&!str_contains($optOut,'Store::tx('),
    'decline-restore page does not mutate production records');

ok(str_contains($restore,'Repair required records in this order'),'recovery page explains remediation order');
ok(str_contains($restore,"'/restore':\$url.'/edit'"),'archived parents link to Restore and suspended parents to Edit');
ok(str_contains($restore,'The linked $label is archived (REMOVED)'),'generic blocker identifies archived parent rather than a vague rejection');
ok(str_contains($restore,"in_array(\$type,['products','organisations','applications','product-tenants'],true)"),'restored parents open their Edit screen for deliberate activation');

ok(str_contains($restore,'$role!==\'SUPER_ADMIN\''),'canonical recovery restricted to Platform super administrators');
ok(str_contains($restore,'$canonical=self::canonicalDomain($record)'),'archived canonical record classified against application base URL');
ok(str_contains($restore,'tenant_product_guid')&&str_contains($restore,'application_product_guid'),'canonical recovery checks product/organisation consistency');
ok(str_contains($restore,'$parents[$name.\'_status\']!==\'ACTIVE\''),'all parent entities must be active before domain restoration');
ok(str_contains($restore,"'/admin/onboarding?domain='.rawurlencode(\$guid)"),'restored canonical hostname returns to verification, not unsafe mapping');

ok(str_contains($onboarding,"'/admin/domains/'.rawurlencode(\$guid).'/restore'"),'duplicate archived hostname from onboarding goes to recovery');
ok(str_contains($app,"'/admin/domains/'.rawurlencode(\$existingGuid).'/restore'"),'duplicate archived hostname from domain registry goes to recovery');
ok(str_contains($ui,'archivedTable($archived)'),'archived publisher inventory visible');
ok(str_contains($app,'DomainTools::publicHostname((string)'),'domain registry normalizes pasted URLs');

ok(str_contains($app,'private function reuseExisting('),'duplicate records share one safe recovery redirect');

$cleanup=source('app/Admin/RegistryCleanup.php');
$actions=source('app/Admin/ActionUi.php');
ok(str_contains($app,'RegistryCleanup::run('),'super-admin resource cleanup route wired before CRUD dispatch');
ok(str_contains($actions,"/release"),'archived rows show permanent release alongside Restore');
ok(str_contains($cleanup,'superAdmin()'),'cleanup requires super administrator permission');
ok(str_contains($cleanup,'Store::tx(')&&str_contains($cleanup,'self::audit('),'cleanup and durable audit share a transaction');
ok(str_contains($cleanup,'DELETE FROM domains WHERE product_guid=? AND status="REMOVED"'),'archived domain registrations are physically removed');
ok(str_contains($cleanup,'DELETE FROM applications WHERE product_guid=? AND status="REMOVED"'),'archived applications are physically removed');
ok(str_contains($cleanup,'UPDATE products SET product_key=?'),'archived products release unique keys while retaining billed identity');
ok(str_contains($cleanup,'subscriptions')&&str_contains($cleanup,'invoices')&&str_contains($cleanup,'payments'),
    'financial record tables participate in retention checks');
ok(!str_contains($cleanup,"DELETE FROM invoices")&&!str_contains($cleanup,"DELETE FROM payments")
    &&!str_contains($cleanup,"DELETE FROM subscriptions")&&!str_contains($cleanup,"DELETE FROM audit_events"),
    'cleanup never deletes immutable financial/audit records');
ok(str_contains($cleanup,"\$retained>0"),'retained billing lineage prevents deletion of original product GUID');

foreach(['organisations','products','applications','productTenants','users'] as $method)
    ok(str_contains(methodSource($app,$method),'$this->reuseExisting('),
        'existing '. $method .' record opens Edit or Restore rather than duplicate INSERT');

ok(str_contains($onboarding,'DomainTools::publicHostname((string)'),'onboarding normalizes pasted URLs');
ok(str_contains($app,'AND a.product_guid=d.product_guid')&&
    str_contains($app,'AND pt.product_guid=d.product_guid AND pt.organisation_guid=d.organisation_guid'),'API rejects product and organisation mismatches');
ok(str_contains($onboarding,'tenant_product_guid')&&str_contains($onboarding,'tenant_organisation_guid'),'onboarding rejects mismatched tenant ownership');
ok(str_contains($mapping,'Current newspaper unavailable — select a publisher'),'missing current tenant cannot silently select first alternative');
ok(str_contains($mapping,'Current application unavailable — select a compatible application'),'missing current application cannot silently select first alternative');
ok(str_contains($schema,'UNIQUE KEY uk_domain_hostname(normalized_hostname)'),'domain hostname uniqueness preserved');

ok(str_contains($schema,'UNIQUE KEY uk_product_tenant_org(organisation_guid,product_guid)'),'tenant uniqueness preserved by organisation/product');

ok(str_contains($schema,'fk_domain_tenant')&&str_contains($schema,'fk_domain_app'),'tenant and application foreign keys preserved');
foreach(['organisationRemove','userRemove','productRemove','applicationRemove','tenantRemove','planRemove'] as $name)
    ok(str_contains(methodSource($crud,$name),'Store::tx('),'transactional archive: '.$name);
ok(str_contains(methodSource($crud,'serviceAccountRotate'),'ensureNotRemoved('),'archived service account cannot be rotated directly');
ok(str_contains(methodSource($crud,'userEdit'),'if($new!==\'\'&&strlen($new)<10)'),'user edit validates password length before mutation');
ok(str_contains(methodSource($crud,'userEdit'),'Store::tx('),'user edits are atomic');
ok(str_contains(source('app/Admin/StagingReset.php'),"Config::get('app.allow_staging_reset',false)===true"),'staging reset remains opt-in and isolated');

echo "Platform recovery source and runtime audit checks passed.\n";
+'domainGuid\x27),'archived domain is independently releasable');
$optOut=methodSource($restore,'freshStartAlternative');
ok(!str_contains($optOut,'Store::exec(')&&!str_contains($optOut,'Store::tx('),
    'decline-restore page does not mutate production records');

ok(str_contains($restore,'Repair required records in this order'),'recovery page explains remediation order');
ok(str_contains($restore,"'/restore':\$url.'/edit'"),'archived parents link to Restore and suspended parents to Edit');
ok(str_contains($restore,'The linked $label is archived (REMOVED)'),'generic blocker identifies archived parent rather than a vague rejection');
ok(str_contains($restore,"in_array(\$type,['products','organisations','applications','product-tenants'],true)"),'restored parents open their Edit screen for deliberate activation');

ok(str_contains($restore,'$role!==\'SUPER_ADMIN\''),'canonical recovery restricted to Platform super administrators');
ok(str_contains($restore,'$canonical=self::canonicalDomain($record)'),'archived canonical record classified against application base URL');
ok(str_contains($restore,'tenant_product_guid')&&str_contains($restore,'application_product_guid'),'canonical recovery checks product/organisation consistency');
ok(str_contains($restore,'$parents[$name.\'_status\']!==\'ACTIVE\''),'all parent entities must be active before domain restoration');
ok(str_contains($restore,"'/admin/onboarding?domain='.rawurlencode(\$guid)"),'restored canonical hostname returns to verification, not unsafe mapping');

ok(str_contains($onboarding,"'/admin/domains/'.rawurlencode(\$guid).'/restore'"),'duplicate archived hostname from onboarding goes to recovery');
ok(str_contains($app,"'/admin/domains/'.rawurlencode(\$existingGuid).'/restore'"),'duplicate archived hostname from domain registry goes to recovery');
ok(str_contains($ui,'archivedTable($archived)'),'archived publisher inventory visible');
ok(str_contains($app,'DomainTools::publicHostname((string)'),'domain registry normalizes pasted URLs');

ok(str_contains($app,'private function reuseExisting('),'duplicate records share one safe recovery redirect');

$cleanup=source('app/Admin/RegistryCleanup.php');
$actions=source('app/Admin/ActionUi.php');
ok(str_contains($app,'RegistryCleanup::run('),'super-admin resource cleanup route wired before CRUD dispatch');
ok(str_contains($actions,"/release"),'archived rows show permanent release alongside Restore');
ok(str_contains($cleanup,'superAdmin()'),'cleanup requires super administrator permission');
ok(str_contains($cleanup,'Store::tx(')&&str_contains($cleanup,'self::audit('),'cleanup and durable audit share a transaction');
ok(str_contains($cleanup,'DELETE FROM domains WHERE product_guid=? AND status="REMOVED"'),'archived domain registrations are physically removed');
ok(str_contains($cleanup,'DELETE FROM applications WHERE product_guid=? AND status="REMOVED"'),'archived applications are physically removed');
ok(str_contains($cleanup,'UPDATE products SET product_key=?'),'archived products release unique keys while retaining billed identity');
ok(str_contains($cleanup,'subscriptions')&&str_contains($cleanup,'invoices')&&str_contains($cleanup,'payments'),
    'financial record tables participate in retention checks');
ok(!str_contains($cleanup,"DELETE FROM invoices")&&!str_contains($cleanup,"DELETE FROM payments")
    &&!str_contains($cleanup,"DELETE FROM subscriptions")&&!str_contains($cleanup,"DELETE FROM audit_events"),
    'cleanup never deletes immutable financial/audit records');
ok(str_contains($cleanup,"\$retained>0"),'retained billing lineage prevents deletion of original product GUID');

foreach(['organisations','products','applications','productTenants','users'] as $method)
    ok(str_contains(methodSource($app,$method),'$this->reuseExisting('),
        'existing '. $method .' record opens Edit or Restore rather than duplicate INSERT');

ok(str_contains($onboarding,'DomainTools::publicHostname((string)'),'onboarding normalizes pasted URLs');
ok(str_contains($app,'AND a.product_guid=d.product_guid')&&
    str_contains($app,'AND pt.product_guid=d.product_guid AND pt.organisation_guid=d.organisation_guid'),'API rejects product and organisation mismatches');
ok(str_contains($onboarding,'tenant_product_guid')&&str_contains($onboarding,'tenant_organisation_guid'),'onboarding rejects mismatched tenant ownership');
ok(str_contains($mapping,'Current newspaper unavailable — select a publisher'),'missing current tenant cannot silently select first alternative');
ok(str_contains($mapping,'Current application unavailable — select a compatible application'),'missing current application cannot silently select first alternative');
ok(str_contains($schema,'UNIQUE KEY uk_domain_hostname(normalized_hostname)'),'domain hostname uniqueness preserved');

ok(str_contains($schema,'UNIQUE KEY uk_product_tenant_org(organisation_guid,product_guid)'),'tenant uniqueness preserved by organisation/product');

ok(str_contains($schema,'fk_domain_tenant')&&str_contains($schema,'fk_domain_app'),'tenant and application foreign keys preserved');
foreach(['organisationRemove','userRemove','productRemove','applicationRemove','tenantRemove','planRemove'] as $name)
    ok(str_contains(methodSource($crud,$name),'Store::tx('),'transactional archive: '.$name);
ok(str_contains(methodSource($crud,'serviceAccountRotate'),'ensureNotRemoved('),'archived service account cannot be rotated directly');
ok(str_contains(methodSource($crud,'userEdit'),'if($new!==\'\'&&strlen($new)<10)'),'user edit validates password length before mutation');
ok(str_contains(methodSource($crud,'userEdit'),'Store::tx('),'user edits are atomic');
ok(str_contains(source('app/Admin/StagingReset.php'),"Config::get('app.allow_staging_reset',false)===true"),'staging reset remains opt-in and isolated');

echo "Platform recovery source and runtime audit checks passed.\n";
+'productGuid\x27),'archived product is directly linked to resource release');
ok(str_contains($restore,'original GUID references, invoices, payments, refunds'),'cleanup alternative explains immutable history');
ok(str_contains($restore,"'/admin/domains/'.\$domainGuid.'/release'"),'archived domain is independently releasable');
$optOut=methodSource($restore,'freshStartAlternative');
ok(!str_contains($optOut,'Store::exec(')&&!str_contains($optOut,'Store::tx('),
    'decline-restore page does not mutate production records');

ok(str_contains($restore,'Repair required records in this order'),'recovery page explains remediation order');
ok(str_contains($restore,"'/restore':\$url.'/edit'"),'archived parents link to Restore and suspended parents to Edit');
ok(str_contains($restore,'The linked $label is archived (REMOVED)'),'generic blocker identifies archived parent rather than a vague rejection');
ok(str_contains($restore,"in_array(\$type,['products','organisations','applications','product-tenants'],true)"),'restored parents open their Edit screen for deliberate activation');

ok(str_contains($restore,'$role!==\'SUPER_ADMIN\''),'canonical recovery restricted to Platform super administrators');
ok(str_contains($restore,'$canonical=self::canonicalDomain($record)'),'archived canonical record classified against application base URL');
ok(str_contains($restore,'tenant_product_guid')&&str_contains($restore,'application_product_guid'),'canonical recovery checks product/organisation consistency');
ok(str_contains($restore,'$parents[$name.\'_status\']!==\'ACTIVE\''),'all parent entities must be active before domain restoration');
ok(str_contains($restore,"'/admin/onboarding?domain='.rawurlencode(\$guid)"),'restored canonical hostname returns to verification, not unsafe mapping');

ok(str_contains($onboarding,"'/admin/domains/'.rawurlencode(\$guid).'/restore'"),'duplicate archived hostname from onboarding goes to recovery');
ok(str_contains($app,"'/admin/domains/'.rawurlencode(\$existingGuid).'/restore'"),'duplicate archived hostname from domain registry goes to recovery');
ok(str_contains($ui,'archivedTable($archived)'),'archived publisher inventory visible');
ok(str_contains($app,'DomainTools::publicHostname((string)'),'domain registry normalizes pasted URLs');

ok(str_contains($app,'private function reuseExisting('),'duplicate records share one safe recovery redirect');

$cleanup=source('app/Admin/RegistryCleanup.php');
$actions=source('app/Admin/ActionUi.php');
ok(str_contains($app,'RegistryCleanup::run('),'super-admin resource cleanup route wired before CRUD dispatch');
ok(str_contains($actions,"/release"),'archived rows show permanent release alongside Restore');
ok(str_contains($cleanup,'superAdmin()'),'cleanup requires super administrator permission');
ok(str_contains($cleanup,'Store::tx(')&&str_contains($cleanup,'self::audit('),'cleanup and durable audit share a transaction');
ok(str_contains($cleanup,'DELETE FROM domains WHERE product_guid=? AND status="REMOVED"'),'archived domain registrations are physically removed');
ok(str_contains($cleanup,'DELETE FROM applications WHERE product_guid=? AND status="REMOVED"'),'archived applications are physically removed');
ok(str_contains($cleanup,'UPDATE products SET product_key=?'),'archived products release unique keys while retaining billed identity');
ok(str_contains($cleanup,'subscriptions')&&str_contains($cleanup,'invoices')&&str_contains($cleanup,'payments'),
    'financial record tables participate in retention checks');
ok(!str_contains($cleanup,"DELETE FROM invoices")&&!str_contains($cleanup,"DELETE FROM payments")
    &&!str_contains($cleanup,"DELETE FROM subscriptions")&&!str_contains($cleanup,"DELETE FROM audit_events"),
    'cleanup never deletes immutable financial/audit records');
ok(str_contains($cleanup,"\$retained>0"),'retained billing lineage prevents deletion of original product GUID');

foreach(['organisations','products','applications','productTenants','users'] as $method)
    ok(str_contains(methodSource($app,$method),'$this->reuseExisting('),
        'existing '. $method .' record opens Edit or Restore rather than duplicate INSERT');

ok(str_contains($onboarding,'DomainTools::publicHostname((string)'),'onboarding normalizes pasted URLs');
ok(str_contains($app,'AND a.product_guid=d.product_guid')&&
    str_contains($app,'AND pt.product_guid=d.product_guid AND pt.organisation_guid=d.organisation_guid'),'API rejects product and organisation mismatches');
ok(str_contains($onboarding,'tenant_product_guid')&&str_contains($onboarding,'tenant_organisation_guid'),'onboarding rejects mismatched tenant ownership');
ok(str_contains($mapping,'Current newspaper unavailable — select a publisher'),'missing current tenant cannot silently select first alternative');
ok(str_contains($mapping,'Current application unavailable — select a compatible application'),'missing current application cannot silently select first alternative');
ok(str_contains($schema,'UNIQUE KEY uk_domain_hostname(normalized_hostname)'),'domain hostname uniqueness preserved');

ok(str_contains($schema,'UNIQUE KEY uk_product_tenant_org(organisation_guid,product_guid)'),'tenant uniqueness preserved by organisation/product');

ok(str_contains($schema,'fk_domain_tenant')&&str_contains($schema,'fk_domain_app'),'tenant and application foreign keys preserved');
foreach(['organisationRemove','userRemove','productRemove','applicationRemove','tenantRemove','planRemove'] as $name)
    ok(str_contains(methodSource($crud,$name),'Store::tx('),'transactional archive: '.$name);
ok(str_contains(methodSource($crud,'serviceAccountRotate'),'ensureNotRemoved('),'archived service account cannot be rotated directly');
ok(str_contains(methodSource($crud,'userEdit'),'if($new!==\'\'&&strlen($new)<10)'),'user edit validates password length before mutation');
ok(str_contains(methodSource($crud,'userEdit'),'Store::tx('),'user edits are atomic');
ok(str_contains(source('app/Admin/StagingReset.php'),"Config::get('app.allow_staging_reset',false)===true"),'staging reset remains opt-in and isolated');

echo "Platform recovery source and runtime audit checks passed.\n";
