<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Tenants;

use Digiti\Core\{Store,Uuid};
use Digiti\Domain\Tenants\TenantLifecyclePolicy;

final class TenantRepository
{
    /** Return hostnames for cache invalidation only after the transaction commits. */
    public function change(string $guid,string $status,?string $name=null,?string $external=null): array
    {
        return Store::tx(static function()use($guid,$status,$name,$external):array{
            $existing=Store::one('SELECT status FROM product_tenants WHERE product_tenant_guid=? FOR UPDATE',[Uuid::toBinary($guid)]);
            if(!$existing)throw new \DomainException('Product tenant not found.');
            TenantLifecyclePolicy::transition($existing['status'],$status);
            $domains=Store::all('SELECT hostname FROM domains WHERE product_tenant_guid=?',[Uuid::toBinary($guid)]);
            if($name!==null){
                Store::exec('UPDATE product_tenants SET display_name=?,external_tenant_reference=?,status=?,updated_at=NOW(6) WHERE product_tenant_guid=?',[$name,$external,$status,Uuid::toBinary($guid)]);
            }else{
                Store::exec('UPDATE product_tenants SET status=?,updated_at=NOW(6) WHERE product_tenant_guid=?',[$status,Uuid::toBinary($guid)]);
            }
            if($status==='REMOVED')
                Store::exec('UPDATE domains SET status="REMOVED",is_primary=0,updated_at=NOW(6) WHERE product_tenant_guid=?',[Uuid::toBinary($guid)]);
            \Digiti\Application\Integration\CacheInvalidationOutbox::stageForTenant($guid);
            return array_column($domains,'hostname');
        });
    }
}
