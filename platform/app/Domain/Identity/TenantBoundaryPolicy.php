<?php
declare(strict_types=1);
namespace Digiti\Domain\Identity;

/** No I/O: active product, organisation, tenant and service boundaries. */
final class TenantBoundaryPolicy
{
    public static function denyReason(array $application,array $tenant,array $service): ?string
    {
        if(($application['status']??null)!=='ACTIVE')return 'AUTH_CLIENT_INVALID';
        foreach(['status','organisation_status','product_status'] as $key)
            if(($tenant[$key]??null)!=='ACTIVE')return 'TENANT_INACTIVE';
        if(empty($application['product_guid'])||empty($tenant['product_guid'])
            ||!hash_equals((string)$application['product_guid'],(string)$tenant['product_guid']))
            return 'AUTH_PRODUCT_MISMATCH';
        // Direct sign-in must never use an unbound platform-wide machine token.
        if(empty($service['product_guid'])
            ||!hash_equals((string)$service['product_guid'],(string)$application['product_guid']))
            return 'SERVICE_PRODUCT_MISMATCH';
        return null;
    }
}
