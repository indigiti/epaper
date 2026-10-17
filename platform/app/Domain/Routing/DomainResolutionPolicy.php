<?php
declare(strict_types=1);
namespace Digiti\Domain\Routing;

/** Authorization and referential invariants for SaaS domain-to-tenant resolution. */
final class DomainResolutionPolicy
{
    public static function denyReason(array $domain,array $service): ?string
    {
        if(($domain['status']??null)!=='ACTIVE')
            return 'DOMAIN_NOT_ACTIVE';
        foreach(['product_status','application_status','tenant_status','organisation_status'] as $name) {
            if(($domain[$name]??null)!=='ACTIVE')return 'DOMAIN_NOT_ACTIVE';
        }
        $product=(string)($domain['product_guid']??'');
        $org=(string)($domain['organisation_guid']??'');
        // Never resolve a hostname whose product/application/tenant references disagree.
        if($product===''||$org===''||!self::same($product,$domain['application_product_guid']??null)
            ||!self::same($product,$domain['tenant_product_guid']??null)
            ||!self::same($org,$domain['tenant_organisation_guid']??null))
            return 'DOMAIN_NOT_ACTIVE';
        // Prevent service credentials registered to one SaaS product probing other products.
        if(!self::same($product,$service['product_guid']??null))return 'DOMAIN_NOT_ACTIVE';
        return null;
    }
    private static function same(string $left,mixed $right): bool
    {
        return $left!==''&&is_string($right)&&$right!==''&&hash_equals($left,$right);
    }
}
