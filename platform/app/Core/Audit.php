<?php
namespace Digiti\Core;

final class Audit
{
    public static function log(string $action,string $resourceType,?string $resourceGuid=null,string $result='SUCCESS',array $meta=[]): void
    {
        try {
            $admin=Auth::admin();
            Store::exec('INSERT INTO audit_events(audit_event_guid,occurred_at,actor_type,actor_user_guid,action,resource_type,resource_guid,result,request_guid,ip_address,user_agent,metadata) VALUES(?,NOW(6),?,?,?,?,?,?,?,?,?,?)',[
                Uuid::toBinary(Uuid::v7()), $admin?'PLATFORM_ADMIN':'SYSTEM', $admin?Uuid::toBinary($admin['admin_guid']):null, $action,$resourceType,$resourceGuid?Uuid::toBinary($resourceGuid):null,$result,Uuid::toBinary(Http::requestGuid()),$_SERVER['REMOTE_ADDR']??'',substr($_SERVER['HTTP_USER_AGENT']??'',0,255),json_encode($meta,JSON_UNESCAPED_SLASHES)
            ]);
        } catch (\Throwable) {}
    }
}
