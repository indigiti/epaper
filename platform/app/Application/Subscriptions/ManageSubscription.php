<?php
declare(strict_types=1);
namespace Digiti\Application\Subscriptions;

use Digiti\Core\{Audit,RedisStore,Uuid};
use Digiti\Domain\Subscriptions\SubscriptionAccessPolicy as Policy;
use Digiti\Infrastructure\Subscriptions\SubscriptionRepository;

/** Subscription mutations; Admin HTTP handlers only provide the requested values. */
final class ManageSubscription
{
    public static function activate(string $tenantGuid,string $planGuid,?SubscriptionRepository $repo=null): string
    {
        if(!Uuid::isValid($tenantGuid)||!Uuid::isValid($planGuid))
            throw new \InvalidArgumentException('Select a valid tenant and plan.');
        $guid=Uuid::v7();
        ($repo??new SubscriptionRepository())->activate($tenantGuid,$planGuid,$guid);
        RedisStore::del('entitlements:'.$tenantGuid);
        Audit::log('subscription.create','subscription',$guid,'SUCCESS',['product_tenant_guid'=>$tenantGuid]);
        return $guid;
    }

    public static function update(string $guid,array $input,?SubscriptionRepository $repo=null): void
    {
        if(!Uuid::isValid($guid))throw new \InvalidArgumentException('Invalid subscription reference.');
        $version=$input['version']??null;
        if(!is_scalar($version)||!ctype_digit((string)$version)||(int)$version<1)
            throw new \InvalidArgumentException('Missing or invalid form version. Refresh before saving.');
        $date=trim((string)($input['current_period_end']??''));
        $end=\DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i',$date,new \DateTimeZone('UTC'));
        if(!$end||$end->format('Y-m-d\\TH:i')!==$date)
            throw new \InvalidArgumentException('Valid period end is required.');
        $values=[
            'status'=>Policy::allowed((string)($input['status']??''),Policy::STATUSES,'subscription status'),
            'billing_state'=>Policy::allowed((string)($input['billing_state']??''),Policy::BILLING,'billing state'),
            'service_access_state'=>Policy::allowed((string)($input['service_access_state']??''),Policy::ACCESS,'access state'),
            'current_period_end'=>$end->format('Y-m-d H:i:s'),
            'cancel_at_period_end'=>!empty($input['cancel_at_period_end'])?1:0
        ];
        $tenantGuid=($repo??new SubscriptionRepository())->update($guid,(int)$version,$values);
        RedisStore::del('entitlements:'.$tenantGuid);
        Audit::log('subscription.update','subscription',$guid,'SUCCESS',['version_before'=>(int)$version]);
    }
}
