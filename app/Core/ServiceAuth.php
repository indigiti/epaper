<?php
namespace Digiti\Core;

final class ServiceAuth
{
    public static function requireScope(string $scope): array
    {
        $token=$_SERVER['HTTP_X_DIGITI_SERVICE_TOKEN']??'';
        if(!$token) Http::json(['ok'=>false,'code'=>'SERVICE_AUTH_REQUIRED','message'=>'Service token required'],401);
        $hash=hash('sha256',$token,true);
        $row=Store::one('SELECT service_account_guid,name,scopes,status,product_guid FROM service_accounts WHERE token_hash=? LIMIT 1',[$hash]);
        if(!$row||$row['status']!=='ACTIVE') Http::json(['ok'=>false,'code'=>'SERVICE_AUTH_INVALID','message'=>'Invalid service token'],401);
        $scopes=json_decode($row['scopes']?:'[]',true)?:[];
        if(!in_array('*',$scopes,true)&&!in_array($scope,$scopes,true)) Http::json(['ok'=>false,'code'=>'SERVICE_SCOPE_DENIED','message'=>'Service scope denied'],403);
        Store::exec('UPDATE service_accounts SET last_used_at=NOW(6) WHERE service_account_guid=?',[Uuid::toBinary($row['service_account_guid'])]);
        return $row;
    }
}
