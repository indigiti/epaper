<?php
declare(strict_types=1);
namespace Digiti\Application\Routing;

use Digiti\Domain\Routing\DomainResolutionPolicy;
use Digiti\Infrastructure\Routing\DomainRepository;

/** Revalidate active relationships on each control-plane lookup. Product caches are separate. */
final class ResolveDomain
{
    public static function execute(string $hostname,array $service,?DomainRepository $repository=null): array
    {
        $row=($repository??new DomainRepository())->findByHostname($hostname);
        if(!$row||DomainResolutionPolicy::denyReason($row,$service)!==null)
            throw new DomainResolutionFailure();
        return [
            'domain_guid'=>$row['domain_guid'],
            'organisation_guid'=>$row['organisation_guid'],
            'product_guid'=>$row['product_guid'],
            'product_key'=>$row['product_key'],
            'application_guid'=>$row['application_guid'],
            'application_key'=>$row['application_key'],
            'product_tenant_guid'=>$row['product_tenant_guid'],
            'hostname'=>$row['hostname'],
            'status'=>$row['status'],
            'base_url'=>$row['base_url'],
            'default_resource_type'=>$row['default_resource_type'],
            'default_resource_guid'=>$row['default_resource_guid'],
        ];
    }
}
