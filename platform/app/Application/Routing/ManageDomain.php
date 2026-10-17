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
        $bin=Uuid::toBinary($guid);
        Store::tx(static function()use($guid,$bin,$input):void {
            // Serialize primary-domain edits through the parent tenant lock.
            $tenant=Store::value('SELECT product_tenant_guid FROM domains WHERE domain_guid=?',[$bin]);
            if(!is_string($tenant))throw new \DomainException('Domain not found');
            if(!Store::one('SELECT product_tenant_guid FROM product_tenants WHERE product_tenant_guid=? FOR UPDATE',[$tenant]))
                throw new \DomainException('Product tenant not found');
            $row=Store::one('SELECT status,domain_type,product_tenant_guid FROM domains WHERE domain_guid=? FOR UPDATE',[$bin]);
            if(!$row||$row['product_tenant_guid']!==$tenant)throw new \DomainException('Domain changed during edit');
            if($row['status']==='REMOVED')throw new \DomainException('Removed domain cannot be edited');
            $status=DomainLifecyclePolicy::editableStatus($row['status'],(string)($input['status']??$row['status']));
            $type=(string)($input['domain_type']??$row['domain_type']);
            $customerTypes=['CUSTOMER_CUSTOM','CUSTOMER_SUBDOMAIN'];
            if($type!==$row['domain_type'] && (!in_array($type,$customerTypes,true)||!in_array($row['domain_type'],$customerTypes,true)))
                throw new \InvalidArgumentException('Reserved platform domain types cannot be assigned in the customer editor');
            $primary=$status==='ACTIVE'&&!empty($input['is_primary'])?1:0;
            if($primary)Store::exec('UPDATE domains SET is_primary=0,updated_at=NOW(6) WHERE product_tenant_guid=? AND is_primary=1 AND domain_guid<>?',[$tenant,$bin]);
            Store::exec('UPDATE domains SET is_primary=?,domain_type=?,status=?,default_resource_type=?,updated_at=NOW(6) WHERE domain_guid=?',[
                $primary,$type,$status,trim((string)($input['default_resource_type']??''))?:null,$bin]);
            // product_tenant_guid columns are BINARY(16); outbox expects textual UUID.
            CacheInvalidationOutbox::stageForTenant(Uuid::fromBinary($tenant));
        });
        Audit::log('domain.update','domain',$guid);
    }

    /** Explicit, auditable route back to DNS+TLS verification after suspension. */
    public static function resume(string $guid):void
    {
        if(!Uuid::isValid($guid))throw new \InvalidArgumentException('Invalid domain GUID');
        Store::tx(static function()use($guid):void {
            $bin=Uuid::toBinary($guid);
            $row=Store::one('SELECT status,product_tenant_guid FROM domains WHERE domain_guid=? FOR UPDATE',[$bin]);
            if(!$row||$row['status']!=='SUSPENDED')throw new \DomainException('Only suspended domains can resume');
            $changed=Store::exec('UPDATE domains SET status="AWAITING_DNS",is_primary=0,verified_at=NULL,ssl_ready_at=NULL,last_checked_at=NULL,updated_at=NOW(6) WHERE domain_guid=? AND status="SUSPENDED"',[$bin]);
            if($changed!==1)throw new \DomainException('Domain status changed; refresh and retry');
            CacheInvalidationOutbox::stageForTenant(Uuid::fromBinary($row['product_tenant_guid']));
        });
        Audit::log('domain.resume_verification','domain',$guid);
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
                // TLS provider readiness does not replace explicit administrator activation.
                $candidate=(string)($edge['status']??'SSL_PROVISIONING');
                $status=in_array($candidate,['ROUTING_VALID','SSL_PROVISIONING'],true)?$candidate:'SSL_PROVISIONING';
                if($d['status']==='ACTIVE' && in_array($candidate,['ROUTING_VALID','ACTIVE'],true))$status='ACTIVE';
            }catch(\Throwable $e){
                $status='SSL_PROVISIONING';$edge=['provider_error'=>'Edge provisioning temporarily unavailable'];
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
            $bin=Uuid::toBinary($guid);
            $row=Store::one('SELECT status,edge_metadata,product_tenant_guid FROM domains WHERE domain_guid=? FOR UPDATE',[$bin]);
            if(!$row)throw new \DomainException('Domain not found');
            $edge=json_decode((string)($row['edge_metadata']??''),true);
            if(!DomainLifecyclePolicy::canActivate((string)$row['status'],is_array($edge)?$edge:[]))
                throw new \DomainException('DNS and TLS/edge readiness must be confirmed before activation');
            $changed=Store::exec('UPDATE domains SET status="ACTIVE",ssl_ready_at=COALESCE(ssl_ready_at,NOW(6)),activated_at=COALESCE(activated_at,NOW(6)),updated_at=NOW(6) WHERE domain_guid=? AND status IN ("ROUTING_VALID","SSL_PROVISIONING")',[$bin]);
            if($changed!==1)throw new \DomainException('Domain status changed during activation');
            CacheInvalidationOutbox::stageForTenant(Uuid::fromBinary($row['product_tenant_guid']));
        });
        Audit::log('domain.activate','domain',$guid);
    }
}
