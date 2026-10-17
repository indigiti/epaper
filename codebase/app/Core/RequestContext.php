<?php
namespace EpaperSaas\Core;
final class RequestContext
{
    private static ?array $ctx=null;
    public static function set(array $ctx): void{self::$ctx=$ctx;}
    public static function all(): array{return self::$ctx??[];}
    public static function get(string $key,mixed $default=null): mixed{return self::$ctx[$key]??$default;}
    public static function tenantGuid(): string{$v=(string)self::get('tenant_guid','');if(!$v)throw new \RuntimeException('Tenant context missing');return $v;}
    public static function organisationGuid(): string{return (string)self::get('organisation_guid','');}
    public static function domainGuid(): ?string{return self::get('domain_guid');}
    public static function userGuid(): ?string{return self::get('user_guid');}
    public static function membershipGuid(): ?string{return self::get('membership_guid');}
    public static function role(): ?string{return self::get('role_key');}
}
