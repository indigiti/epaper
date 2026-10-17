<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

/**
 * Server-side publisher authorization, independent of menu visibility.
 * Infrastructure handlers also enforce canonical-host ownership.
 */
final class PublisherAccess
{
    public static function allows(string $role, string $path, string $method): bool
    {
        $role=strtoupper(trim($role));
        if (!in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','OPERATOR'],true)) return false;
        if (!in_array(strtoupper($method),['GET','POST'],true)) return false;

        // Cross-tenant/global infrastructure is reserved to the canonical operators.
        if (preg_match('#^/admin/system/(?:infrastructure(?:/|$)|upgrade$|recovery(?:/|$))#',$path)) {
            return in_array($role,['OWNER','ADMIN'],true);
        }

        // High-impact configuration, subscriber and governance changes.
        if (in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN'],true)) return true;

        // General administrative workspaces shared by editor/operator roles.
        if (in_array($path,['/admin','/admin/account','/admin/preferences/theme'],true)) return true;
        if (preg_match('#^/admin/(?:errors$|performance$|system/(?:health|readiness\\.json)$)#',$path)) return true;
        if ($path==='/admin/processing/repair-queue') return false;
        if (preg_match('#^/admin/processing(?:/|$)#',$path)) return true;
        if (preg_match('#^/admin/editions/[0-9a-f-]{36}/(?:process-now|process-immediately|cancel|recover|reprocess)$#i',$path)) return true;

        // Operators can supervise processing, not create publications or change editorial content.
        if ($role==='OPERATOR') return false;

        // Editors may work on editorial content and editions, not user access or system configuration.
        return (bool)preg_match('#^/admin/(?:navigation(?:/|$)|publications(?:/|$)|highlights(?:/|$)|articles(?:/|$)|authors(?:/|$)|topics(?:/|$)|entities(?:/|$)|master-data(?:/|$)|editions(?:/|$))#',$path);
    }
}
