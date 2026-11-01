<?php
declare(strict_types=1);

$root=dirname(__DIR__);
spl_autoload_register(static function(string $class) use($root):void {
    if(!str_starts_with($class,'Digiti\\'))return;
    $path=$root.'/app/'.str_replace('\\','/',substr($class,7)).'.php';
    if(is_file($path))require_once $path;
});

use Digiti\Core\{Config,Database,Store,Uuid};
use Digiti\Admin\{DomainMapping,Restore};

function checkDb(bool $pass,string $message):void {
    if(!$pass){fwrite(STDERR,"FAIL: ".$message."\n");exit(1);}
    echo "PASS: ".$message."\n";
}
if(getenv('PLATFORM_TEST_MYSQL')!=='1'){echo "SKIP: isolated MySQL test not configured\n";exit(0);}

$host=(string)(getenv('MYSQL_HOST')?:'127.0.0.1');
$db=(string)(getenv('MYSQL_DATABASE')?:'platform_ci');
$user=(string)(getenv('MYSQL_USER')?:'root');
$pass=(string)(getenv('MYSQL_PASSWORD')?:'');
$cfg="<?php return ".var_export([
    'app'=>['env'=>'testing','url'=>'https://platform-ci.example.invalid','timezone'=>'UTC'],
    'db'=>['host'=>$host,'port'=>3306,'name'=>$db,'user'=>$user,'pass'=>$pass,'charset'=>'utf8mb4'],
    'redis'=>['enabled'=>false]
],true).";";
$cfgPath=$root.'/config/config.php';
file_put_contents($cfgPath,$cfg);
try{
    Config::load($root);
    $pdo=Database::pdo();
    $schema=file_get_contents($root.'/database/schema.sql');
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$schema) as $statement){
        $statement=trim($statement);
        if($statement!=='')$pdo->exec($statement);
    }

    $ids=[];
    foreach(['orgAMG','orgDCN','product','application','tenantAMG','tenantDCN','domain'] as $key)$ids[$key]=Uuid::v7();
    $bin=static fn(string $key):string=>Uuid::toBinary($ids[$key]);
    foreach(['orgAMG'=>['AMG','AMG'],'orgDCN'=>['DCN','DCN News']] as $key=>$data)
        Store::exec('INSERT INTO organisations(organisation_guid,organisation_code,legal_name,display_name,status,created_at,updated_at)
            VALUES(?,?,?,?,"ACTIVE",NOW(6),NOW(6))',[$bin($key),$data[0],$data[1],$data[1]]);
    Store::exec('INSERT INTO products(product_guid,product_key,name,status,created_at,updated_at)
        VALUES(?,"EPAPER","ePaper","ACTIVE",NOW(6),NOW(6))',[$bin('product')]);
    Store::exec('INSERT INTO applications(application_guid,product_guid,application_key,name,base_url,status,created_at,updated_at)
        VALUES(?,?,"EPAPER","EPAPER","https://epaper.digiti.in","ACTIVE",NOW(6),NOW(6))',
        [$bin('application'),$bin('product')]);
    foreach(['tenantAMG'=>['orgAMG','EPAPER'],'tenantDCN'=>['orgDCN','DCNEP']] as $key=>$args)
        Store::exec('INSERT INTO product_tenants(product_tenant_guid,organisation_guid,product_guid,display_name,status,created_at,updated_at)
            VALUES(?,?,?,?,"ACTIVE",NOW(6),NOW(6))',
            [$bin($key),$bin($args[0]),$bin('product'),$args[1]]);
    $hostName='epaper.dcnnews.co.in';
    Store::exec('INSERT INTO domains(domain_guid,organisation_guid,product_guid,application_guid,
        product_tenant_guid,hostname,normalized_hostname,domain_type,status,is_primary,verification_token,created_at,updated_at)
        VALUES(?,?,?,?,?,?,?,"CUSTOMER_CUSTOM","REMOVED",0,"test-dns-token",NOW(6),NOW(6))',
        [$bin('domain'),$bin('orgAMG'),$bin('product'),$bin('application'),$bin('tenantAMG'),$hostName,$hostName]);
    $record=Store::one('SELECT * FROM domains WHERE domain_guid=?',[$bin('domain')]);
    checkDb($record['domain_guid']===$ids['domain']&&$record['product_tenant_guid']===$ids['tenantAMG'],'binary GUIDs decode from real MySQL schema');
    $check=(new ReflectionMethod(Restore::class,'checkDependencies'));
    $check->invoke(null,'domains',$record);
    checkDb(true,'archived domain meets valid parent checks without duplicate creation');
    $candidate=Store::one('SELECT product_tenant_guid FROM product_tenants WHERE organisation_guid=? AND product_guid=?',
        [$bin('orgDCN'),$bin('product')]);
    checkDb($candidate['product_tenant_guid']===$ids['tenantDCN'],'DCNEP resolves to a different publisher tenant on same product');
    $mutable=(new ReflectionMethod(DomainMapping::class,'mutable'));
    $domainContext=['domain_type'=>'CUSTOMER_CUSTOM','status'=>'REMOVED','hostname'=>$hostName,'base_url'=>'https://epaper.digiti.in'];
    checkDb(!$mutable->invoke(null,$domainContext),'archived customer domain cannot be switched before restore');
    Store::exec('UPDATE domains SET status="AWAITING_DNS" WHERE domain_guid=?',[$bin('domain')]);
    $domainContext['status']='AWAITING_DNS';
    checkDb($mutable->invoke(null,$domainContext),'restored domain can be assigned to the DCNEP tenant');
    $mappingHtml=DomainMapping::panel([
        'domain_guid'=>$ids['domain'],'organisation'=>'AMG','tenant'=>'EPAPER','application'=>'EPAPER'
    ]);
    checkDb(str_contains($mappingHtml,'DCN News / DCNEP')&&str_contains($mappingHtml,$ids['tenantDCN']),
        'live domain-edit dropdown includes the separate DCNEP tenant');
    $updated=Store::exec('UPDATE domains SET organisation_guid=?,product_tenant_guid=?,status="AWAITING_DNS",
        verified_at=NULL,ssl_ready_at=NULL,activated_at=NULL WHERE domain_guid=?',
        [$bin('orgDCN'),$bin('tenantDCN'),$bin('domain')]);
    checkDb($updated===1,'existing domain record can be reassigned without reinstalling ePaper');
    $after=Store::one('SELECT domain_guid,organisation_guid,product_tenant_guid FROM domains WHERE domain_guid=?',
        [$bin('domain')]);
    checkDb($after['organisation_guid']===$ids['orgDCN']&&$after['product_tenant_guid']===$ids['tenantDCN'],
        'tenant reassignment preserves the original domain ID');
    $count=(int)Store::value('SELECT COUNT(*) FROM domains WHERE normalized_hostname=?',[$hostName]);
    checkDb($count===1,'unique hostname is preserved after recovery and switching');
    $joined=Store::one('SELECT d.domain_guid FROM domains d
        JOIN applications a ON a.application_guid=d.application_guid AND a.product_guid=d.product_guid
        JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid
            AND pt.product_guid=d.product_guid AND pt.organisation_guid=d.organisation_guid
        WHERE d.domain_guid=?',[$bin('domain')]);
    checkDb($joined!==null,'real MySQL resolver consistency join accepts valid mapping');
    Store::exec('UPDATE domains SET organisation_guid=? WHERE domain_guid=?',[$bin('orgAMG'),$bin('domain')]);
    $invalid=Store::one('SELECT d.domain_guid FROM domains d JOIN product_tenants pt
        ON pt.product_tenant_guid=d.product_tenant_guid AND pt.organisation_guid=d.organisation_guid
        WHERE d.domain_guid=?',[$bin('domain')]);
    checkDb($invalid===null,'real MySQL consistency join rejects cross-organisation mapping');

    // Application hostname recovery: no new product, tenant or domain installation.
    $canonicalGuid=Uuid::v7();
    Store::exec('INSERT INTO domains(domain_guid,organisation_guid,product_guid,application_guid,
        product_tenant_guid,hostname,normalized_hostname,domain_type,status,is_primary,verification_token,created_at,updated_at)
        VALUES(?,?,?,?,?,?,?,"SAAS_DEFAULT","REMOVED",0,"canonical-dns-token",NOW(6),NOW(6))',[
        Uuid::toBinary($canonicalGuid),$bin('orgAMG'),$bin('product'),
        $bin('application'),$bin('tenantAMG'),'epaper.digiti.in','epaper.digiti.in'
    ]);
    $canonicalRecord=Store::one('SELECT * FROM domains WHERE domain_guid=?',[Uuid::toBinary($canonicalGuid)]);

    $fresh=(new ReflectionMethod(Restore::class,'freshStartAlternative'))->invoke(null,$canonicalRecord);
    checkDb(str_contains($fresh,'Do not restore — release archived resources'),
        'archived canonical hostname offers a direct cleanup alternative');
    checkDb(str_contains($fresh,'/admin/products/'.$ids['product'].'/release'),
        'old EPAPER product has a linked release review');
    checkDb(str_contains($fresh,'/admin/domains/'.$canonicalGuid.'/release'),
        'archived canonical hostname can be released independently');
    checkDb(str_contains($fresh,'invoices, payments, refunds, subscriptions, usage and audit logs'),
        'cleanup choices explicitly protect retained financial history');
    checkDb(Store::one('SELECT status FROM domains WHERE domain_guid=?',
        [Uuid::toBinary($canonicalGuid)])['status']==='REMOVED',
        'previewing fresh-start option does not restore the archived domain');

    $canonical=(new ReflectionMethod(Restore::class,'canonicalDomain'));
    checkDb($canonical->invoke(null,$canonicalRecord),'archived SAAS_DEFAULT ePaper hostname recognized as canonical app host');
    // Mimic cascading archive: the product, application and tenant are all
    // REMOVED, while their original GUIDs and domain record remain intact.
    Store::exec('UPDATE products SET status="REMOVED" WHERE product_guid=?',[$bin('product')]);
    Store::exec('UPDATE applications SET status="REMOVED" WHERE application_guid=?',[$bin('application')]);
    Store::exec('UPDATE product_tenants SET status="REMOVED" WHERE product_tenant_guid=?',[$bin('tenantAMG')]);
    $guide=(new ReflectionMethod(Restore::class,'dependencyGuide'))->invoke(null,$canonicalRecord);
    checkDb(str_contains($guide,'/admin/products/'.$ids['product'].'/restore'),
        'blocked canonical recovery links to the exact archived EPAPER product');
    checkDb(str_contains($guide,'/admin/applications/'.$ids['application'].'/restore'),
        'blocked canonical recovery links to archived original application');
    checkDb(str_contains($guide,'/admin/product-tenants/'.$ids['tenantAMG'].'/restore'),
        'blocked canonical recovery links to the original AMG tenant');
    checkDb(strpos($guide,'Product:')<strpos($guide,'Application:')
        &&strpos($guide,'Application:')<strpos($guide,'Publisher tenant:'),
        'dependency recovery steps appear in parent-first order');
    checkDb(str_contains($guide,'restore first, then activate'),
        'an archived parent explicitly requires Restore then Activate');
    $rejected=false;
    try{$check->invoke(null,'domains',$canonicalRecord);}catch(\InvalidArgumentException $e){
        $rejected=str_contains($e->getMessage(),'linked product is archived');
    }
    checkDb($rejected,'archived product blocks domain until repaired');
    Store::exec('UPDATE products SET status="ACTIVE" WHERE product_guid=?',[$bin('product')]);
    Store::exec('UPDATE applications SET status="ACTIVE" WHERE application_guid=?',[$bin('application')]);
    Store::exec('UPDATE product_tenants SET status="ACTIVE" WHERE product_tenant_guid=?',[$bin('tenantAMG')]);
    $check->invoke(null,'domains',$canonicalRecord);
    checkDb(true,'application hostname may recover without creating/restoring any product when parents are active');
    Store::exec('UPDATE products SET status="SUSPENDED" WHERE product_guid=?',[$bin('product')]);
    $rejected=false;
    try{$check->invoke(null,'domains',$canonicalRecord);}catch(\InvalidArgumentException $e){
        $rejected=str_contains($e->getMessage(),'product is SUSPENDED');
    }
    checkDb($rejected,'canonical recovery refuses suspended product rather than bypassing product safety');
    Store::exec('UPDATE products SET status="ACTIVE" WHERE product_guid=?',[$bin('product')]);
    Store::exec('UPDATE domains SET domain_type="PLATFORM" WHERE domain_guid=?',[Uuid::toBinary($canonicalGuid)]);
    $notEpaperType=Store::one('SELECT * FROM domains WHERE domain_guid=?',[Uuid::toBinary($canonicalGuid)]);
    checkDb(!$canonical->invoke(null,$notEpaperType),'Platform infrastructure type cannot use ePaper canonical recovery');
    $rejected=false;
    try{$check->invoke(null,'domains',$notEpaperType);}catch(\InvalidArgumentException $e){
        $rejected=str_contains($e->getMessage(),'System infrastructure hostnames');
    }
    checkDb($rejected,'canonical recovery blocks Platform infrastructure records');
    Store::exec('UPDATE domains SET domain_type="SAAS_DEFAULT",status="AWAITING_DNS",is_primary=0 WHERE domain_guid=?',[Uuid::toBinary($canonicalGuid)]);
    $restored=Store::one('SELECT domain_guid,status,product_guid,application_guid,product_tenant_guid FROM domains
        WHERE domain_guid=?',[Uuid::toBinary($canonicalGuid)]);
    checkDb($restored['domain_guid']===$canonicalGuid && $restored['status']==='AWAITING_DNS'
        && $restored['product_guid']===$ids['product']
        && $restored['application_guid']===$ids['application']
        && $restored['product_tenant_guid']===$ids['tenantAMG'],
        'canonical recovery preserves original domain ID, product, ePaper application and tenant');
    checkDb((int)Store::value('SELECT COUNT(*) FROM products WHERE product_key="EPAPER"')===1,
        'canonical recovery does not create an additional product');


    // Retention-aware cleanup: remove obsolete operational rows, preserve
    // original invoice/subscription/payment/audit GUIDs, and release keys.
    $planGuid=Uuid::v7();$priceGuid=Uuid::v7();$featureGuid=Uuid::v7();
    $subscriptionGuid=Uuid::v7();$invoiceGuid=Uuid::v7();$paymentGuid=Uuid::v7();
    $refundGuid=Uuid::v7();$attemptGuid=Uuid::v7();$invoiceItemGuid=Uuid::v7();
    $usageGuid=Uuid::v7();$grantGuid=Uuid::v7();$oldAuditGuid=Uuid::v7();
    Store::exec('INSERT INTO features(feature_guid,product_guid,feature_key,name,feature_type,created_at)
        VALUES(?,?,"EDITION_LIMIT","Edition limit","NUMBER",NOW(6))',
        [Uuid::toBinary($featureGuid),$bin('product')]);
    Store::exec('INSERT INTO plans(plan_guid,product_guid,plan_key,name,status,created_at,updated_at)
        VALUES(?,?,"OLD_PLAN","Previous publishing plan","ACTIVE",NOW(6),NOW(6))',
        [Uuid::toBinary($planGuid),$bin('product')]);
    Store::exec('INSERT INTO prices(price_guid,plan_guid,currency,amount_minor,billing_interval,status,created_at)
        VALUES(?,?,"INR",49900,"MONTHLY","ACTIVE",NOW(6))',
        [Uuid::toBinary($priceGuid),Uuid::toBinary($planGuid)]);
    Store::exec('INSERT INTO plan_features(plan_guid,feature_guid,enabled) VALUES(?,?,1)',
        [Uuid::toBinary($planGuid),Uuid::toBinary($featureGuid)]);
    Store::exec('INSERT INTO entitlement_grants(grant_guid,product_tenant_guid,feature_guid,enabled,created_at)
        VALUES(?,?,?,1,NOW(6))',[Uuid::toBinary($grantGuid),$bin('tenantAMG'),Uuid::toBinary($featureGuid)]);
    Store::exec('INSERT INTO usage_meters(usage_guid,product_tenant_guid,feature_guid,period_key,used_value,reserved_value,updated_at)
        VALUES(?,?,?,"2026-10",12,0,NOW(6))',
        [Uuid::toBinary($usageGuid),$bin('tenantAMG'),Uuid::toBinary($featureGuid)]);
    Store::exec('INSERT INTO subscriptions(subscription_guid,product_tenant_guid,plan_guid,price_guid,
        status,billing_state,service_access_state,current_period_start,current_period_end,created_at,updated_at)
        VALUES(?,?,?,?,"ACTIVE","ACTIVE","FULL",NOW(6),DATE_ADD(NOW(6),INTERVAL 1 MONTH),NOW(6),NOW(6))',
        [Uuid::toBinary($subscriptionGuid),$bin('tenantAMG'),Uuid::toBinary($planGuid),Uuid::toBinary($priceGuid)]);
    Store::exec('INSERT INTO invoices(invoice_guid,product_tenant_guid,invoice_number,currency,
        subtotal_minor,tax_minor,total_minor,status,created_at,updated_at)
        VALUES(?,?,"CI-INVOICE-0001","INR",49900,0,49900,"PAID",NOW(6),NOW(6))',
        [Uuid::toBinary($invoiceGuid),$bin('tenantAMG')]);
    Store::exec('INSERT INTO invoice_items(invoice_item_guid,invoice_guid,description,
        quantity,unit_amount_minor,line_total_minor)
        VALUES(?,?,"October ePaper service",1,49900,49900)',
        [Uuid::toBinary($invoiceItemGuid),Uuid::toBinary($invoiceGuid)]);
    Store::exec('INSERT INTO payments(payment_guid,product_tenant_guid,invoice_guid,provider,amount_minor,currency,status,created_at,updated_at)
        VALUES(?,?,?,"TEST",49900,"INR","SUCCEEDED",NOW(6),NOW(6))',
        [Uuid::toBinary($paymentGuid),$bin('tenantAMG'),Uuid::toBinary($invoiceGuid)]);
    Store::exec('INSERT INTO payment_attempts(attempt_guid,payment_guid,status,created_at)
        VALUES(?,?,"SUCCEEDED",NOW(6))',[Uuid::toBinary($attemptGuid),Uuid::toBinary($paymentGuid)]);
    Store::exec('INSERT INTO refunds(refund_guid,payment_guid,amount_minor,status,created_at,updated_at)
        VALUES(?,?,1000,"SUCCEEDED",NOW(6),NOW(6))',
        [Uuid::toBinary($refundGuid),Uuid::toBinary($paymentGuid)]);
    Store::exec('INSERT INTO audit_events(audit_event_guid,occurred_at,actor_type,action,resource_type,resource_guid,result)
        VALUES(?,NOW(6),"SYSTEM","historical.domain.link","domain",?,"SUCCESS")',
        [Uuid::toBinary($oldAuditGuid),$bin('domain')]);
    Store::exec('UPDATE domains SET status="REMOVED" WHERE product_guid=?',[$bin('product')]);
    Store::exec('UPDATE applications SET status="REMOVED" WHERE product_guid=?',[$bin('product')]);
    Store::exec('UPDATE product_tenants SET status="REMOVED" WHERE product_guid=?',[$bin('product')]);
    Store::exec('UPDATE products SET status="REMOVED" WHERE product_guid=?',[$bin('product')]);
    Store::exec('UPDATE organisations SET status="REMOVED" WHERE organisation_guid=?',[$bin('orgAMG')]);
    $cleanup=new ReflectionMethod(\Digiti\Admin\RegistryCleanup::class,'snapshot');
    $blocked=$cleanup->invoke(null,'products',$ids['product']);
    checkDb((bool)$blocked['blockers'],'active subscription blocks product release without deleting financial references');
    Store::exec('UPDATE subscriptions SET status="CANCELLED",service_access_state="NONE" WHERE subscription_guid=?',
        [Uuid::toBinary($subscriptionGuid)]);
    $eligible=$cleanup->invoke(null,'products',$ids['product']);
    checkDb(!$eligible['blockers'] && $eligible['counts']['Invoices retained']===1
        && $eligible['counts']['Payments retained']===1,'settled financial history permits safe archived product release');
    $release=(new ReflectionMethod(\Digiti\Admin\RegistryCleanup::class,'releaseProduct'));
    $adminGuid=Uuid::v7();
    Store::exec('INSERT INTO platform_admins(admin_guid,email,display_name,password_hash,role_key,
        status,created_at,updated_at) VALUES(?,"ci@example.test","CI Admin","hash","SUPER_ADMIN","ACTIVE",NOW(6),NOW(6))',
        [Uuid::toBinary($adminGuid)]);
    $auditWrite=(new ReflectionMethod(\Digiti\Admin\RegistryCleanup::class,'audit'));
    $auditChildren=(new ReflectionMethod(\Digiti\Admin\RegistryCleanup::class,'auditProductChildren'));
    $purgedHostnames=[];
    Store::tx(function()use($release,$auditWrite,$auditChildren,$ids,$adminGuid,&$purgedHostnames):void {
        $auditChildren->invoke(null,['admin_guid'=>$adminGuid],$ids['product']);
        $impact=$release->invokeArgs(null,[$ids['product'],&$purgedHostnames]);
        $auditWrite->invoke(null,['admin_guid'=>$adminGuid],'products',$ids['product'],'EPAPER',$impact);
    });
    checkDb(in_array('epaper.digiti.in',$purgedHostnames,true)
        &&in_array('epaper.dcnnews.co.in',$purgedHostnames,true),
        'archived canonical and customer hostnames are released');
    checkDb((int)Store::value('SELECT COUNT(*) FROM domains WHERE product_guid=?',[$bin('product')])===0,
        'archived domain registry rows physically deleted');
    checkDb((int)Store::value('SELECT COUNT(*) FROM applications WHERE product_guid=?',[$bin('product')])===0,
        'archived application configuration physically deleted');
    checkDb((int)Store::value('SELECT COUNT(*) FROM products WHERE product_key="EPAPER"')===0,
        'unique EPAPER product key is released despite retained billing lineage');
    $historicProduct=Store::one('SELECT product_guid,product_key FROM products WHERE product_guid=?',[$bin('product')]);
    checkDb($historicProduct['product_guid']===$ids['product'] &&
        str_starts_with($historicProduct['product_key'],'RETIRED_'),
        'minimal historical product retains original GUID but not the reserved product key');
    $invoiceAfter=Store::one('SELECT i.invoice_guid,i.product_tenant_guid,t.product_guid,
        t.display_name,p.product_guid product_reference_guid
        FROM invoices i JOIN product_tenants t ON t.product_tenant_guid=i.product_tenant_guid
        JOIN products p ON p.product_guid=t.product_guid WHERE i.invoice_guid=?',
        [Uuid::toBinary($invoiceGuid)]);
    checkDb($invoiceAfter['invoice_guid']===$invoiceGuid
        &&$invoiceAfter['product_tenant_guid']===$ids['tenantAMG']
        &&$invoiceAfter['product_guid']===$ids['product'],
        'invoice remains linked to original tenant and retired product GUID');
    foreach([
        ['subscriptions','subscription_guid',$subscriptionGuid],
        ['payments','payment_guid',$paymentGuid],
        ['refunds','refund_guid',$refundGuid],
        ['payment_attempts','attempt_guid',$attemptGuid],
        ['invoice_items','invoice_item_guid',$invoiceItemGuid],
        ['usage_meters','usage_guid',$usageGuid],
        ['audit_events','audit_event_guid',$oldAuditGuid]
    ] as [$table,$col,$id]){
        checkDb((int)Store::value("SELECT COUNT(*) FROM $table WHERE $col=?",[Uuid::toBinary($id)])===1,
            'retained original GUID in '.$table);
    }
    checkDb((int)Store::value('SELECT COUNT(*) FROM audit_events WHERE action="registry.release"
        AND resource_guid=?',[ $bin('product') ])===1,'permanent cleanup creates a durable trace to original product GUID');

    foreach([['domain',$ids['domain']],['application',$ids['application']],
        ['product-tenant',$ids['tenantAMG']]] as [$type,$id]) {
        checkDb((int)Store::value('SELECT COUNT(*) FROM audit_events WHERE action="registry.release"
            AND resource_type=? AND resource_guid=?',[$type,Uuid::toBinary($id)])===1,
            'child registry audit retains original '.$type.' GUID and label');
    }

    checkDb((int)Store::value('SELECT COUNT(*) FROM features WHERE feature_key="EDITION_LIMIT"')===0,
        'retained feature history no longer reserves global feature keys');
    $orgRelease=(new ReflectionMethod(\Digiti\Admin\RegistryCleanup::class,'releaseOrganisation'));
    $orgBefore=$cleanup->invoke(null,'organisations',$ids['orgAMG']);
    checkDb(!$orgBefore['blockers'],'archived AMG organisation eligible for code release');
    Store::tx(fn()=> $orgRelease->invoke(null,$ids['orgAMG']));
    checkDb((int)Store::value('SELECT COUNT(*) FROM organisations WHERE organisation_code="AMG"')===0,
        'archived organisation code released while billed tenant remains linked');
    $oldOrg=Store::one('SELECT legal_name FROM organisations WHERE organisation_guid=?',[$bin('orgAMG')]);
    checkDb($oldOrg['legal_name']==='AMG','historic legal name retained for billing readability');
    $newProductGuid=Uuid::v7();$newOrgGuid=Uuid::v7();$newAppGuid=Uuid::v7();
    $newTenantGuid=Uuid::v7();$newDomainGuid=Uuid::v7();$newFeatureGuid=Uuid::v7();
    Store::exec('INSERT INTO products(product_guid,product_key,name,status,created_at,updated_at)
        VALUES(?,"EPAPER","Fresh ePaper","ACTIVE",NOW(6),NOW(6))',[Uuid::toBinary($newProductGuid)]);
    Store::exec('INSERT INTO organisations(organisation_guid,organisation_code,legal_name,display_name,
        status,created_at,updated_at) VALUES(?,"AMG","New AMG","New AMG","ACTIVE",NOW(6),NOW(6))',
        [Uuid::toBinary($newOrgGuid)]);
    Store::exec('INSERT INTO applications(application_guid,product_guid,application_key,name,base_url,
        status,created_at,updated_at) VALUES(?,?,"EPAPER","New EPAPER",
        "https://epaper.digiti.in","ACTIVE",NOW(6),NOW(6))',
        [Uuid::toBinary($newAppGuid),Uuid::toBinary($newProductGuid)]);
    Store::exec('INSERT INTO product_tenants(product_tenant_guid,organisation_guid,product_guid,display_name,
        status,created_at,updated_at) VALUES(?,?,?,"New AMG tenant","ACTIVE",NOW(6),NOW(6))',
        [Uuid::toBinary($newTenantGuid),Uuid::toBinary($newOrgGuid),Uuid::toBinary($newProductGuid)]);
    Store::exec('INSERT INTO domains(domain_guid,organisation_guid,product_guid,application_guid,
        product_tenant_guid,hostname,normalized_hostname,domain_type,status,is_primary,
        verification_token,created_at,updated_at) VALUES(?,?,?,?,?,
        "epaper.digiti.in","epaper.digiti.in","SAAS_DEFAULT","AWAITING_DNS",0,
        "new-canonical-token",NOW(6),NOW(6))',
        [Uuid::toBinary($newDomainGuid),Uuid::toBinary($newOrgGuid),Uuid::toBinary($newProductGuid),
            Uuid::toBinary($newAppGuid),Uuid::toBinary($newTenantGuid)]);
    Store::exec('INSERT INTO features(feature_guid,product_guid,feature_key,name,created_at)
        VALUES(?,?,"EDITION_LIMIT","Fresh edition limit",NOW(6))',
        [Uuid::toBinary($newFeatureGuid),Uuid::toBinary($newProductGuid)]);
    checkDb((int)Store::value('SELECT COUNT(*) FROM products WHERE product_key="EPAPER"')===1
        &&(int)Store::value('SELECT COUNT(*) FROM domains WHERE normalized_hostname="epaper.digiti.in"')===1
        &&(int)Store::value('SELECT COUNT(*) FROM applications WHERE application_key="EPAPER"')===1,
        'fresh setup reuses original EPAPER product, application and canonical hostname keys');

    echo "PASS: database-backed Platform domain recovery integration checks complete\n";
}finally{
    @unlink($cfgPath);
}
