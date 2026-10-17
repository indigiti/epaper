<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

use EpaperSaas\Core\{RequestContext,Store,Uuid};
use EpaperSaas\Services\{EditionManifest,MultiLayerCache,PublicReadCache,SeoDiscovery};

$warm=in_array('--warm',$argv,true);$summary=['tenants'=>0,'runtime_removed'=>0,'manifest_removed'=>0,'warmed'=>0,'errors'=>0];
$tenants=Store::all('SELECT tenant_guid,display_name,organisation_guid FROM ep_tenants WHERE status="ACTIVE" ORDER BY created_at');
foreach($tenants as$t){
    try{
        $tenantGuid=Uuid::fromBinary($t['tenant_guid']);RequestContext::set(['tenant_guid'=>$tenantGuid,'tenant_name'=>$t['display_name']??'Publisher','organisation_guid'=>!empty($t['organisation_guid'])?Uuid::fromBinary($t['organisation_guid']):null,'request_guid'=>Uuid::v7(),'correlation_guid'=>Uuid::v7()]);
        $summary['tenants']++;$r=MultiLayerCache::cleanup();$m=EditionManifest::cleanup();$summary['runtime_removed']+=(int)$r['removed'];$summary['manifest_removed']+=(int)$m['removed'];
        if($warm){PublicReadCache::warm();EditionManifest::warmPublished(20);SeoDiscovery::warm();$summary['warmed']++;}
    }catch(Throwable$e){$summary['errors']++;fwrite(STDERR,"tenant cache maintenance failed: {$e->getMessage()}\n");}
}
echo json_encode($summary,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";exit($summary['errors']?1:0);
