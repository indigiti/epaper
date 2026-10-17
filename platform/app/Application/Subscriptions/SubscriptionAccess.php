<?php
declare(strict_types=1);
namespace Digiti\Application\Subscriptions;

use Digiti\Domain\Subscriptions\SubscriptionAccessPolicy;
use Digiti\Infrastructure\Subscriptions\SubscriptionRepository;

/** Fresh DB authorization decision; Redis entitlement cache is never an access grant. */
final class SubscriptionAccess
{
    public static function payload(string $tenantGuid,?SubscriptionRepository $repo=null): array
    {
        $row=($repo??new SubscriptionRepository())->currentAccess($tenantGuid);
        $state=SubscriptionAccessPolicy::effective($row);
        if($row){
            foreach(['tenant_status','organisation_status','product_status','period_valid'] as $key)unset($row[$key]);
            $row['service_access_state']=$state;
        }
        return ['ok'=>true,'product_tenant_guid'=>$tenantGuid,'subscription'=>$row,'service_access_state'=>$state];
    }
}
