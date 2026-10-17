<?php
declare(strict_types=1);
namespace Digiti\Application\Security;

use Digiti\Core\{Audit,Store,Uuid};

/** One-time support token, tenant-bound and race-safe under MariaDB transactions. */
final class ConsumeImpersonation
{
    public static function consume(string $token,string $tenantGuid): array
    {
        if(!Uuid::isValid($tenantGuid)||$token==='')
            throw new \DomainException('IMPERSONATION_INVALID');
        $identity=Store::tx(static function()use($tenantGuid,$token):array{
            $tenant=Store::one('SELECT pt.organisation_guid FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid AND o.status="ACTIVE" WHERE pt.product_tenant_guid=? AND pt.status="ACTIVE"',[Uuid::toBinary($tenantGuid)]);
            if(!$tenant)throw new \DomainException('TENANT_NOT_FOUND');
            $grant=Store::one('SELECT impersonation_guid,admin_guid,organisation_guid,user_guid,reason FROM impersonation_grants WHERE token_hash=? AND used_at IS NULL AND revoked_at IS NULL AND expires_at>NOW(6) FOR UPDATE',[hash('sha256',$token,true)]);
            if(!$grant||$grant['organisation_guid']!==$tenant['organisation_guid'])
                throw new \DomainException('IMPERSONATION_INVALID');
            $membership=Store::one('SELECT membership_guid,organisation_guid,user_guid,role_key,status FROM memberships WHERE organisation_guid=? AND user_guid=? AND status="ACTIVE"',[Uuid::toBinary($grant['organisation_guid']),Uuid::toBinary($grant['user_guid'])]);
            $user=Store::one('SELECT user_guid,email,display_name,status FROM platform_users WHERE user_guid=? AND status="ACTIVE"',[Uuid::toBinary($grant['user_guid'])]);
            if(!$membership||!$user)throw new \DomainException('MEMBERSHIP_NOT_ACTIVE');
            $updated=Store::exec('UPDATE impersonation_grants SET used_at=NOW(6) WHERE impersonation_guid=? AND used_at IS NULL AND revoked_at IS NULL AND expires_at>NOW(6)',[Uuid::toBinary($grant['impersonation_guid'])]);
            if($updated!==1)throw new \DomainException('IMPERSONATION_INVALID');
            return ['user'=>$user,'membership'=>$membership,'impersonator_admin_guid'=>$grant['admin_guid'],
                'reason'=>$grant['reason'],'grant_guid'=>$grant['impersonation_guid']];
        });
        Audit::log('impersonation.consume','impersonation_grant',$identity['grant_guid'],'SUCCESS',['product_tenant_guid'=>$tenantGuid]);
        unset($identity['grant_guid']);
        return $identity;
    }
}
