<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Metering;

use Digiti\Core\{Store,Uuid};

final class UsageRepository
{
    public function record(string $tenantGuid,string $featureKey,string $period,string $delta): void
    {
        $feature=Store::one('SELECT f.feature_guid FROM features f
            JOIN product_tenants pt ON pt.product_tenant_guid=? AND pt.status="ACTIVE"
            WHERE f.feature_key=? AND (f.product_guid IS NULL OR f.product_guid=pt.product_guid) LIMIT 1',
            [Uuid::toBinary($tenantGuid),$featureKey]);
        if(!$feature)throw new \DomainException('FEATURE_NOT_FOUND');
        // MariaDB unique (tenant, feature, period) serializes counter increments.
        Store::exec('INSERT INTO usage_meters
            (usage_guid,product_tenant_guid,feature_guid,period_key,used_value,reserved_value,updated_at)
            VALUES(?,?,?,?,GREATEST(0,CAST(? AS DECIMAL(20,4))),0,NOW(6))
            ON DUPLICATE KEY UPDATE
            used_value=GREATEST(0,used_value+CAST(? AS DECIMAL(20,4))),updated_at=NOW(6)',
            [Uuid::toBinary(Uuid::v7()),Uuid::toBinary($tenantGuid),
             Uuid::toBinary($feature['feature_guid']),$period,$delta,$delta]);
    }
}
