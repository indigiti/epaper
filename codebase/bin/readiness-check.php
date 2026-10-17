<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

use EpaperSaas\Core\{Config,RequestContext,Store,Uuid};
use EpaperSaas\Services\ProductionReadiness;

$tenant='';$json=in_array('--json',$argv,true);
foreach($argv as$arg)if(str_starts_with($arg,'--tenant='))$tenant=trim(substr($arg,9));
if($tenant==='')$tenant=trim((string)Config::get('platform.default_tenant_guid',''));
if(!Uuid::isValid($tenant)){
    fwrite(STDERR,"Usage: php bin/readiness-check.php --tenant=<tenant-guid> [--json]\n");
    exit(2);
}
$row=Store::one('SELECT tenant_guid,organisation_guid,display_name,status FROM ep_tenants WHERE tenant_guid=?',[Uuid::toBinary($tenant)]);
if(!$row){fwrite(STDERR,"Tenant not found\n");exit(2);}
RequestContext::set(['tenant_guid'=>$tenant,'tenant_name'=>$row['display_name']??'Publisher','organisation_guid'=>!empty($row['organisation_guid'])?Uuid::fromBinary($row['organisation_guid']):null,'request_guid'=>Uuid::v7(),'correlation_guid'=>Uuid::v7()]);
$report=ProductionReadiness::snapshot(false);
if($json){echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";exit($report['ok']?0:1);}
echo "EP-DI {$report['version']} production readiness: {$report['status']}\n";
foreach($report['checks'] as$c){printf("%-16s %-28s %-16s %s\n",$c['category'],$c['name'],$c['status'],$c['detail']);}
exit($report['ok']?0:1);
