<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Routing;

use Digiti\Core\Store;

/** Database-authoritative domain lookup; avoids stale authorization via Redis cache. */
final class DomainRepository
{
    public function findByHostname(string $hostname): ?array
    {
        return Store::one(
            'SELECT d.domain_guid,d.organisation_guid,d.product_guid,d.application_guid,d.product_tenant_guid,d.hostname,d.status,d.is_primary,d.default_resource_type,d.default_resource_guid,p.product_key,p.status product_status,a.application_key,a.base_url,a.status application_status,a.product_guid application_product_guid,pt.status tenant_status,pt.product_guid tenant_product_guid,pt.organisation_guid tenant_organisation_guid,o.status organisation_status
             FROM domains d
             JOIN products p ON p.product_guid=d.product_guid
             JOIN applications a ON a.application_guid=d.application_guid
             JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid
             JOIN organisations o ON o.organisation_guid=d.organisation_guid
             WHERE d.normalized_hostname=? LIMIT 1',[$hostname]);
    }
}
