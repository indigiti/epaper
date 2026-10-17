<?php
declare(strict_types=1);
namespace Digiti\Application\Routing;

use Digiti\Core\{Audit,DomainTools,RedisStore,Store,Uuid};
use Digiti\Domain\Routing\DomainLifecyclePolicy;
use Digiti\Domain\EdgeProviderFactory;
use Digiti\Application\Integration\CacheInvalidationOutbox;

/** HTTP-independent lifecycle orchestration for Admin Domain actions. */
final class ManageDomain
{
    public static function edit(string $guid,array $input):void
    {
        if(!Uuid::isValid($guid))throw new \InvalidArgumentException('Invalid domain GUID');
        Store::tx(static function()use($guid,$input):void{
            $row=Store::one('SELECT hostname,status,product_tenant_guid FROM domains WHERE domain_guid=? FOR UPDATE',[Uuid::toBinary($guid)]);
            if(!$row)throw new \DomainException('Domain not found');
            $status=DomainLifecyclePolicy::editableStatus($row['status'],(string)($input['status']??$row['status']));
            $domainType=(string)($input['domain_type']??'CUSTOMER_CUSTOM');
            if(!in_array($domainType,['PLATFORM','PLATFORM_AUTH','SAAS_DEFAULT','CUSTOMER_CUSTOM','CUSTOMER_SUBDOMAIN'],true))
                throw new \InvalidArgumentException('Invalid domain type');
            $primary=$status==='ACTIVE'&&!empty($input['is_primary'])?1:0;
            Store::exec('UPDATE domains SET is_primary=?,domain_type=?,status=?,default_resource_type=?,updated_at=NOW(6) WHERE domain_guid=?',[
                $primary,$domainType,$status,trim((string)($input['default_resource_type']??''))?:null,Uuid::toBinary($guid)]);
            CacheInvalidationOutbox::stageForTenant($row['product_tenant_guid']);
        });
        Audit::log('domain.update','domain',$guid);
    }

    public static function verify(string $guid):array
    {
        if(!Uuid::isValid($guid))throw new \InvalidArgumentException('Invalid domain GUID');
        $d=Store::one('SELECT hostname,verification_token,status,edge_metadata FROM domains WHERE domain_guid=?',[Uuid::toBinary($guid)]);
        if(!$d||in_array($d['status'],['REMOVED','SUSPENDED'],true))throw new \DomainException('Domain cannot be verified in its current state');
        $dns=DomainTools::verifyDns($d['hostname'],$d['verification_token']);
        $status='AWAITING_DNS';$edge=[];
        if($dns['verified']){
            try{
                $edge=EdgeProviderFactory::make()->provision($d['hostname'],json_decode($d['edge_metadata']?:'{}',true)?:[]);
                $status=(string)($edge['status']??'ROUTING_VALID');
            }catch(\Throwable $e){
                $status='ROUTING_VALID';$edge=['provider_error'=>$e->getMessage()];
            }
        }
        Store::tx(static function()use($guid,$d,$dns,$status,$edge):void{
            $changed=Store::exec('UPDATE domains SET status=?,dns_metadata=?,edge_metadata=?,verified_at=IF(?<>"AWAITING_DNS",COALESCE(verified_at,NOW(6)),verified_at),last_checked_at=NOW(6),updated_at=NOW(6) WHERE domain_guid=? AND status=?',[
                $status,json_encode($dns,JSON_THROW_ON_ERROR),json_encode($edge,JSON_THROW_ON_ERROR),$status,Uuid::toBinary($guid),$d['status']]);
            if($changed!==1)throw new \DomainException('Domain changed during verification; retry on fresh state');
            $tenant=Store::value('SELECT product_tenant_guid FROM domains WHERE domain_guid=?',[Uuid::toBinary($guid)]);
            if(is_string($tenant))CacheInvalidationOutbox::stageForTenant(Uuid::fromBinary($tenant));
        });
        Audit::log('domain.verify','domain',$guid,empty($dns['verified'])?'FAILED':'SUCCESS');
        return ['verified'=>(bool)($dns['verified']??false),'status'=>$status];
    }

    public static function activate(string $guid):void
    {
        if(!Uuid::isValid($guid))throw new \InvalidArgumentException('Invalid domain GUID');
        Store::tx(static function()use($guid):void{
            $changed=Store::exec('UPDATE domains SET status="ACTIVE",ssl_ready_at=COALESCE(ssl_ready_at,NOW(6)),activated_at=COALESCE(activated_at,NOW(6)),updated_at=NOW(6) WHERE domain_guid=? AND status IN ("ROUTING_VALID","SSL_PROVISIONING")',[Uuid::toBinary($guid)]);
            if($changed!==1)throw new \DomainException('Domain is not verified or state changed');
            $tenant=Store::value('SELECT product_tenant_guid FROM domains WHERE domain_guid=?',[Uuid::toBinary($guid)]);
            if(is_string($tenant))CacheInvalidationOutbox::stageForTenant(Uuid::fromBinary($tenant));
        });
        Audit::log('domain.activate','domain',$guid);
    }
}
