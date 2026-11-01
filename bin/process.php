<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

use EpaperSaas\Core\{RequestContext,Store,Uuid};
use EpaperSaas\Services\Processing;

$args=[];
foreach(array_slice($argv,1) as $a){
    if(str_contains($a,'=')){
        [$k,$v]=explode('=',$a,2);
        $args[ltrim($k,'-')]=$v;
    }
}
$tenant=$args['tenant']??'';
$edition=$args['edition']??'';
$job=$args['job']??null;
if(!$tenant||!$edition){
    fwrite(STDERR,"Usage: php bin/process.php --tenant=<uuid> --edition=<uuid> [--job=<uuid>]\n");
    exit(2);
}
$t=Store::one('SELECT display_name,organisation_guid FROM ep_tenants WHERE tenant_guid=?',[Uuid::toBinary($tenant)]);
if(!$t){fwrite(STDERR,"Tenant not found\n");exit(2);}
RequestContext::set([
    'tenant_guid'=>$tenant,
    'tenant_name'=>$t['display_name'],
    'organisation_guid'=>$t['organisation_guid']?Uuid::fromBinary($t['organisation_guid']):null,
    'request_guid'=>Uuid::v7(),
    'correlation_guid'=>Uuid::v7()
]);
try{
    $r=(new Processing())->processEdition($edition,$job);
    echo json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
    exit(!empty($r['already_running'])?3:0);
}catch(Throwable $e){
    fwrite(STDERR,$e->getMessage()."\n");
    exit(1);
}
