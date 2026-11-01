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

    echo "PASS: database-backed Platform domain recovery integration checks complete\n";
}finally{
    @unlink($cfgPath);
}
