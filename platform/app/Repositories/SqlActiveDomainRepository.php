<?php
declare(strict_types=1);
namespace Digiti\Repositories;
use Digiti\Contracts\ActiveDomainReader;
use Digiti\Core\Store;
final class SqlActiveDomainRepository implements ActiveDomainReader
{
    public function byHostname(string $normalizedHostname): ?array
    {
        return Store::one('SELECT d.domain_guid,d.organisation_guid,d.product_guid,d.application_guid,d.product_tenant_guid,d.hostname,d.status,d.is_primary,d.default_resource_type,d.default_resource_guid,p.product_key,a.application_key,a.base_url FROM domains d JOIN products p ON p.product_guid=d.product_guid AND p.status="ACTIVE" JOIN applications a ON a.application_guid=d.application_guid AND a.status="ACTIVE" JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid AND pt.status="ACTIVE" JOIN organisations o ON o.organisation_guid=d.organisation_guid AND o.status="ACTIVE" WHERE d.normalized_hostname=? LIMIT 1',[$normalizedHostname]);
    }
}
