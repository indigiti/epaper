<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Metering;

use Digiti\Core\{Store,Uuid};

/** Atomic, at-most-once metering per stable caller-supplied event GUID. */
final class UsageRepository
{
    public function record(string $tenantGuid,string $featureKey,string $period,string $delta,string $eventGuid): bool
    {
        return Store::tx(static function()use($tenantGuid,$featureKey,$period,$delta,$eventGuid):bool {
            $feature=Store::one('SELECT f.feature_guid FROM features f
                JOIN product_tenants pt ON pt.product_tenant_guid=? AND pt.status="ACTIVE"
                JOIN organisations o ON o.organisation_guid=pt.organisation_guid AND o.status="ACTIVE"
                JOIN products p ON p.product_guid=pt.product_guid AND p.status="ACTIVE"
                WHERE f.feature_key=? AND (f.product_guid IS NULL OR f.product_guid=pt.product_guid) LIMIT 1',
                [Uuid::toBinary($tenantGuid),$featureKey]);
            if(!$feature)throw new \DomainException('FEATURE_NOT_FOUND');
            $featureGuid=$feature['feature_guid'];
            $digest=hash('sha256',implode('|',[$tenantGuid,$featureGuid,$period,$delta]),true);
            $inserted=Store::exec('INSERT INTO usage_events(event_guid,product_tenant_guid,feature_guid,period_key,delta,payload_hash,created_at)
                VALUES(?,?,?,?,?,?,NOW(6))
                ON DUPLICATE KEY UPDATE event_guid=event_guid',
                [Uuid::toBinary($eventGuid),Uuid::toBinary($tenantGuid),Uuid::toBinary($featureGuid),$period,$delta,$digest]);
            if($inserted===0){
                $prior=Store::one('SELECT payload_hash FROM usage_events WHERE event_guid=? FOR UPDATE',[Uuid::toBinary($eventGuid)]);
                if(!$prior||!is_string($prior['payload_hash'])||!hash_equals($prior['payload_hash'],$digest))
                    throw new \DomainException('USAGE_EVENT_CONFLICT');
                return false;
            }
            if($inserted!==1)throw new \RuntimeException('Unexpected usage event insert status');
            Store::exec('INSERT INTO usage_meters
                (usage_guid,product_tenant_guid,feature_guid,period_key,used_value,reserved_value,updated_at)
                VALUES(?,?,?,?,GREATEST(0,CAST(? AS DECIMAL(20,4))),0,NOW(6))
                ON DUPLICATE KEY UPDATE
                used_value=GREATEST(0,used_value+CAST(? AS DECIMAL(20,4))),updated_at=NOW(6)',
                [Uuid::toBinary(Uuid::v7()),Uuid::toBinary($tenantGuid),
                 Uuid::toBinary($featureGuid),$period,$delta,$delta]);
            return true;
        });
    }
}
