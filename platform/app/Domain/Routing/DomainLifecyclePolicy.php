<?php
declare(strict_types=1);
namespace Digiti\Domain\Routing;

/** Edit cannot bypass DNS/TLS activation, or resurrect a removed domain. */
final class DomainLifecyclePolicy
{
    /** TLS readiness is required before granting an active hostname. */
    public static function canActivate(string $status,array $edge): bool
    {
        if($status==='ROUTING_VALID')
            return ($edge['provider']??null)==='manual' && ($edge['status']??null)==='ROUTING_VALID';
        if($status==='SSL_PROVISIONING')
            return ($edge['status']??null)==='ACTIVE' && ($edge['provider']??null)==='cloudflare';
        return false;
    }

    public static function editableStatus(string $current,string $requested): string
    {
        if($current==='REMOVED'&&$requested!=='REMOVED')
            throw new \DomainException('Removed domains cannot be reactivated.');
        if($requested===$current)return $current;
        // Removal is an explicit action, guaranteeing is_primary is cleared.
        if($requested==='SUSPENDED'&&$current!=='REMOVED')return $requested;
        throw new \DomainException('Reactivate domains through DNS Verify and Activate, not Edit.');
    }
}
