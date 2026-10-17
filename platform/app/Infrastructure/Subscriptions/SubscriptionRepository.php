<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Subscriptions;

use Digiti\Core\{Store,Uuid};

/** Optimistic Admin writes, serial tenant activation and DB-authoritative access. */
final class SubscriptionRepository
{
    public function activate(string $tenantGuid,string $planGuid,string $newGuid): void
    {
        Store::tx(static function()use($tenantGuid,$planGuid,$newGuid):void {
            $tenant=Store::one('SELECT pt.product_guid,pt.status tenant_status,o.status organisation_status,p.status product_status FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid JOIN products p ON p.product_guid=pt.product_guid WHERE pt.product_tenant_guid=? FOR UPDATE',[Uuid::toBinary($tenantGuid)]);
            if(!$tenant||$tenant['tenant_status']!=='ACTIVE'||$tenant['organisation_status']!=='ACTIVE'||$tenant['product_status']!=='ACTIVE')
                throw new \DomainException('Tenant, organisation and product must all be active.');
            $plan=Store::one('SELECT product_guid,status FROM plans WHERE plan_guid=?',[Uuid::toBinary($planGuid)]);
            if(!$plan||$plan['status']!=='ACTIVE'||$tenant['product_guid']!==$plan['product_guid'])
                throw new \DomainException('Plan must be active and belong to the tenant product.');
            $current=(int)Store::value('SELECT COUNT(*) FROM subscriptions WHERE product_tenant_guid=? AND status IN ("ACTIVE","PAST_DUE","GRACE_PERIOD")',[Uuid::toBinary($tenantGuid)]);
            if($current>0)throw new \DomainException('Tenant already has a current subscription; edit that subscription instead.');
            Store::exec('INSERT INTO subscriptions(subscription_guid,product_tenant_guid,plan_guid,status,billing_state,service_access_state,current_period_start,current_period_end,created_at,updated_at) VALUES(?,?,?,"ACTIVE","ACTIVE","FULL",NOW(6),DATE_ADD(NOW(6),INTERVAL 1 MONTH),NOW(6),NOW(6))',[Uuid::toBinary($newGuid),Uuid::toBinary($tenantGuid),Uuid::toBinary($planGuid)]);
        });
    }

    public function update(string $guid,int $expectedVersion,array $values): string
    {
        return Store::tx(static function()use($guid,$expectedVersion,$values):string {
            $row=Store::one('SELECT product_tenant_guid,version FROM subscriptions WHERE subscription_guid=? FOR UPDATE',[Uuid::toBinary($guid)]);
            if(!$row)throw new \DomainException('Subscription not found.');
            if((int)$row['version']!==$expectedVersion)
                throw new \DomainException('Subscription changed since this form was opened. Refresh before saving.');
            $changed=Store::exec('UPDATE subscriptions SET status=?,billing_state=?,service_access_state=?,current_period_end=?,cancel_at_period_end=?,version=version+1,updated_at=NOW(6) WHERE subscription_guid=? AND version=?',[
                $values['status'],$values['billing_state'],$values['service_access_state'],
                $values['current_period_end'],$values['cancel_at_period_end'],
                Uuid::toBinary($guid),$expectedVersion
            ]);
            if($changed!==1)throw new \DomainException('Subscription changed during update. Refresh and try again.');
            return $row['product_tenant_guid'];
        });
    }

    public function currentAccess(string $tenantGuid): ?array
    {
        return Store::one('SELECT s.subscription_guid,s.status,s.billing_state,s.service_access_state,s.current_period_start,s.current_period_end,
            pt.status tenant_status,o.status organisation_status,p.status product_status,
            CASE WHEN NOW(6)>=s.current_period_start AND NOW(6)<s.current_period_end THEN 1 ELSE 0 END period_valid
            FROM subscriptions s
            JOIN product_tenants pt ON pt.product_tenant_guid=s.product_tenant_guid
            JOIN organisations o ON o.organisation_guid=pt.organisation_guid
            JOIN products p ON p.product_guid=pt.product_guid
            WHERE s.product_tenant_guid=? ORDER BY s.created_at DESC LIMIT 1',[Uuid::toBinary($tenantGuid)]);
    }
}
