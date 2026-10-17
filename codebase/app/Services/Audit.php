<?php
namespace EpaperSaas\Services;
use EpaperSaas\Core\{Http,RequestContext,Store,Uuid};

/**
 * Tenant-safe audit writer.
 *
 * resource_guid is a BINARY(16) UUID column. Some product surfaces also have
 * human/string identifiers (settings groups, schema versions, probe tokens).
 * Those are preserved as metadata resource_ref instead of being coerced into
 * UUID storage. This keeps audit logging from breaking the business request.
 */
final class Audit
{
    public static function log(string $action,string $resourceType='system',?string $resourceGuid=null,string $result='SUCCESS',array $meta=[]): void
    {
        $ctx=RequestContext::all();
        $auditGuid=Uuid::v7();
        $tenant=self::validUuidOrNull($ctx['tenant_guid']??null);
        $actorRaw=$ctx['user_guid']??($_SESSION['identity']['user']['user_guid']??null);
        $actor=self::validUuidOrNull($actorRaw);
        $membership=self::validUuidOrNull($ctx['membership_guid']??null);
        $resource=self::validUuidOrNull($resourceGuid);

        // Preserve non-UUID identifiers rather than throwing Invalid UUID.
        if($resourceGuid!==null&&$resource===null&&trim($resourceGuid)!=='')$meta['resource_ref']=$resourceGuid;
        if($actorRaw!==null&&$actor===null)$meta['actor_ref']=(string)$actorRaw;
        if(($ctx['membership_guid']??null)!==null&&$membership===null)$meta['membership_ref']=(string)$ctx['membership_guid'];

        $request=Http::requestGuid();
        $correlation=Http::correlationGuid();
        if(!Uuid::isValid($request))$request=Uuid::v7();
        if(!Uuid::isValid($correlation))$correlation=Uuid::v7();

        Store::exec(
            'INSERT INTO ep_audit_events(audit_event_guid,tenant_guid,occurred_at,actor_type,actor_user_guid,membership_guid,action,resource_type,resource_guid,result,request_guid,correlation_guid,metadata_json) VALUES(?,?,NOW(6),?,?,?,?,?,?,?,?,?,?)',
            [
                Uuid::toBinary($auditGuid),
                $tenant?Uuid::toBinary($tenant):null,
                $actor?'USER':'SYSTEM',
                $actor?Uuid::toBinary($actor):null,
                $membership?Uuid::toBinary($membership):null,
                $action,
                $resourceType,
                $resource?Uuid::toBinary($resource):null,
                $result,
                Uuid::toBinary($request),
                Uuid::toBinary($correlation),
                json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            ]
        );

        // Platform audit is a secondary control-plane copy. A transient Platform
        // failure must not turn a successfully committed local request into 500.
        try{
            PlatformClient::instance()->audit([
                'tenant_guid'=>$tenant,
                'actor_type'=>$actor?'USER':'SYSTEM',
                'actor_user_guid'=>$actor,
                'membership_guid'=>$membership,
                'action'=>$action,
                'resource_type'=>$resourceType,
                'resource_guid'=>$resource,
                'result'=>$result,
                'request_guid'=>$request,
                'correlation_guid'=>$correlation,
                'metadata'=>$meta,
            ]);
        }catch(\Throwable){}
    }

    private static function validUuidOrNull(mixed $value): ?string
    {
        if(!is_string($value))return null;
        $value=trim($value);
        return $value!==''&&Uuid::isValid($value)?strtolower($value):null;
    }
}
