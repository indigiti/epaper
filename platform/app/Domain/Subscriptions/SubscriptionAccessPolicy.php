<?php
declare(strict_types=1);
namespace Digiti\Domain\Subscriptions;

/** Pure policy. Persisted access flags are upper bounds, not authorization grants. */
final class SubscriptionAccessPolicy
{
    public const STATUSES=['ACTIVE','PAST_DUE','GRACE_PERIOD','CANCELLED','EXPIRED'];
    public const BILLING=['ACTIVE','PAST_DUE','GRACE_PERIOD','EXPIRED','CANCELLED'];
    public const ACCESS=['FULL','RESTRICTED','BILLING_ONLY','BLOCKED'];

    public static function allowed(string $value,array $choices,string $label): string
    {
        if(!in_array($value,$choices,true))throw new \InvalidArgumentException('Invalid '.$label);
        return $value;
    }

    public static function effective(?array $row): string
    {
        if(!$row)return 'BLOCKED';
        foreach(['tenant_status','organisation_status','product_status'] as $key)
            if(($row[$key]??null)!=='ACTIVE')return 'BLOCKED';
        if(empty($row['period_valid']))return 'BLOCKED';
        $status=(string)($row['status']??'');
        $billing=(string)($row['billing_state']??'');
        $access=(string)($row['service_access_state']??'BLOCKED');
        if(!in_array($status,['ACTIVE','PAST_DUE','GRACE_PERIOD'],true)
            ||!in_array($billing,['ACTIVE','PAST_DUE','GRACE_PERIOD'],true)
            ||!in_array($access,self::ACCESS,true))return 'BLOCKED';
        if($status==='ACTIVE'&&$billing==='ACTIVE')return $access;
        // Never return FULL during arrears or grace, regardless of stored flag.
        return $access==='FULL'?'RESTRICTED':$access;
    }
}
