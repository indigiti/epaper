<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Identity;

use Digiti\Core\{Store,Uuid};

/** SQL adapter: identity reads do not belong in HTTP controllers. */
final class IdentityRepository
{
    public function activeApplication(string $clientId): ?array
    {
        return Store::one('SELECT application_guid,product_guid,application_key,name,status,oauth_client_id FROM applications WHERE oauth_client_id=? AND status="ACTIVE" LIMIT 1',[$clientId]);
    }
    public function tenant(string $guid): ?array
    {
        return Store::one('SELECT pt.product_tenant_guid,pt.organisation_guid,pt.product_guid,pt.display_name,pt.status,o.status organisation_status,p.status product_status FROM product_tenants pt JOIN organisations o ON o.organisation_guid=pt.organisation_guid JOIN products p ON p.product_guid=pt.product_guid WHERE pt.product_tenant_guid=? LIMIT 1',[Uuid::toBinary($guid)]);
    }
    public function activeMemberships(string $userGuid,string $orgGuid): array
    {
        return Store::all('SELECT membership_guid,organisation_guid,role_key,status FROM memberships WHERE user_guid=? AND organisation_guid=? AND status="ACTIVE"',[Uuid::toBinary($userGuid),Uuid::toBinary($orgGuid)]);
    }
}
