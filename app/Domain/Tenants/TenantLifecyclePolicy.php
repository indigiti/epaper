<?php
declare(strict_types=1);
namespace Digiti\Domain\Tenants;

/** Removed tenants cannot be reactivated by a stale Admin form. */
final class TenantLifecyclePolicy
{
    public static function transition(string $old,string $next): string
    {
        if(!in_array($next,['ACTIVE','SUSPENDED','REMOVED'],true))
            throw new \InvalidArgumentException('Invalid tenant status.');
        if($old==='REMOVED'&&$next!=='REMOVED')
            throw new \DomainException('Removed tenant cannot be reactivated.');
        if(!in_array($old,['ACTIVE','SUSPENDED','REMOVED'],true))
            throw new \DomainException('Unknown current tenant lifecycle state.');
        return $next;
    }
}
