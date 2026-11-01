<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

/**
 * Checks the identity of the Platform application that owns this ePaper
 * installation, not the editable product key ("EPAPER", "EP-DI", etc.).
 * Static and configuration-agnostic so release tests can assert both
 * legitimate migrations and cross-application rejection.
 */
final class PlatformBinding
{
    private static function host(string $value): string
    {
        $value=trim($value);
        $host=parse_url($value,PHP_URL_HOST);
        return strtolower(rtrim((string)($host?:$value),'.'));
    }

    public static function check(array $domain,string $requestedHost,array $expected): array
    {
        $fail=static fn(string $code):array=>['ok'=>false,'code'=>$code];
        $host=self::host($requestedHost);
        $canonical=self::host((string)($expected['canonical_url']??''));
        if($host===''||$canonical==='')return $fail('LOCAL_CANONICAL_URL_MISSING');
        $remoteHost=self::host((string)($domain['hostname']??''));
        if($remoteHost!==$host)return $fail('HOSTNAME_MISMATCH');

        $status=strtoupper((string)($domain['status']??''));
        $appGuid=strtolower(trim((string)($domain['application_guid']??'')));
        $productGuid=strtolower(trim((string)($domain['product_guid']??'')));
        $tenantGuid=strtolower(trim((string)($domain['product_tenant_guid']??'')));
        if($status==='CANONICAL_FALLBACK'){
            // A deliberate emergency-only failover requires GUID pinning.
            if(empty($expected['allow_canonical_fallback'])
                ||$host!==$canonical
                ||!self::validGuid($appGuid)
                ||!self::validGuid($productGuid)
                ||!self::validGuid($tenantGuid)
                ||strtolower((string)($expected['application_guid']??''))!==$appGuid
                ||strtolower((string)($expected['product_guid']??''))!==$productGuid
                ||strtolower((string)($expected['default_tenant_guid']??''))!==$tenantGuid)
                return $fail('UNSAFE_CANONICAL_FALLBACK');
            return ['ok'=>true,'code'=>'EMERGENCY_FALLBACK'];
        }
        if(!in_array($status,['ROUTING_VALID','SSL_PROVISIONING','ACTIVE'],true))
            return $fail('DOMAIN_NOT_ACTIVE');
        if(!self::validGuid($appGuid) || !self::validGuid($productGuid)
            || !self::validGuid($tenantGuid))
            return $fail('DOMAIN_IDENTITY_INCOMPLETE');
        // Every publisher alias must point back to this installed application's
        // canonical base URL. A hostname at another application's URL is denied.
        if(self::host((string)($domain['base_url']??''))!==$canonical)
            return $fail('APPLICATION_BASE_URL_MISMATCH');
        $expectedApp=strtolower(trim((string)($expected['application_guid']??'')));
        if($expectedApp!=='' && $expectedApp!==$appGuid)
            return $fail('CONFIGURED_APPLICATION_GUID_STALE');
        $expectedProduct=strtolower(trim((string)($expected['product_guid']??'')));
        if($expectedProduct!=='' && $expectedProduct!==$productGuid)
            return $fail('CONFIGURED_PRODUCT_GUID_STALE');
        // An optional default tenant pins only the canonical website.
        // Custom publisher hostnames always resolve their own registered tenant.
        $canonicalTenant=strtolower(trim((string)($expected['default_tenant_guid']??'')));
        if($host===$canonical && $canonicalTenant!=='' && $canonicalTenant!==$tenantGuid)
            return $fail('CONFIGURED_CANONICAL_TENANT_STALE');
        return ['ok'=>true,'code'=>'APPLICATION_BOUND'];
    }

    private static function validGuid(string $guid): bool
    {
        return (bool)preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',$guid);
    }
}
