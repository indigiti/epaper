<?php
declare(strict_types=1);
namespace Digiti\Domain\Routing;

/** Edit cannot bypass DNS/TLS activation, or resurrect a removed domain. */
final class DomainLifecyclePolicy
{
    public static function editableStatus(string $current,string $requested): string
    {
        if($current==='REMOVED'&&$requested!=='REMOVED')
            throw new \DomainException('Removed domains cannot be reactivated.');
        if($requested===$current)return $current;
        if($requested==='SUSPENDED'||$requested==='REMOVED')return $requested;
        throw new \DomainException('Reactivate domains through DNS Verify and Activate, not Edit.');
    }
}
