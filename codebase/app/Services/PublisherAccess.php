<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

/**
 * Positive, method-aware policy for publisher console routes.
 * UI menu hiding is not an authorization control.
 */
final class PublisherAccess
{
    public static function allows(string $role,string $path,string $method): bool
    {
        $role=strtoupper(trim($role));
        $method=strtoupper($method);
        if(!in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','OPERATOR'],true))return false;
        if(!in_array($method,['GET','POST'],true))return false;
        if(!str_starts_with($path,'/admin'))return false;

        // Canonical-host infrastructure and destructive cross-queue repair:
        // only owner/admin even when called via direct HTTP or an old UI link.
        if(preg_match('#^/admin/system/(?:infrastructure(?:/|$)|upgrade(?:/|$)|recovery(?:/|$|\\.json$))#',$path)
           || $path==='/admin/processing/repair-queue') {
            return in_array($role,['OWNER','ADMIN'],true);
        }
        if(in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN'],true))return true;

        // Read-only shared operational pages. Exact paths prevent action bypasses.
        if(in_array($path,['/admin','/admin/account','/admin/errors','/admin/performance',
            '/admin/system/health','/admin/system/readiness.json'],true))return $method==='GET';

        // User-specific theme preference is permitted for either role.
        if($path==='/admin/preferences/theme')return $method==='POST';

        // Operators and editors may inspect processing but cannot repair queues
        // or submit arbitrary POST actions under /admin/processing.
        if($path==='/admin/processing' ||
           preg_match('#^/admin/processing/[0-9a-f-]{36}$#i',$path))return $method==='GET';

        // Explicitly bounded edition-processing operations.
        if(preg_match('#^/admin/editions/[0-9a-f-]{36}/(?:process-now|process-immediately|cancel|recover|reprocess)$#i',$path))return $method==='POST';
        if($path==='/admin/editions')return $role==='EDITOR'||$method==='GET';

        if($role==='OPERATOR')return false;

        // Editors can read a publication record, not update/delete a publication.
        if($path==='/admin/publications')return $method==='GET';

        // Existing editorial workflows (navigation, story and edition management).
        return (bool)preg_match('#^/admin/(?:navigation|highlights|articles|authors|topics|entities|master-data|editions)(?:/|$)#',$path);
    }
}
