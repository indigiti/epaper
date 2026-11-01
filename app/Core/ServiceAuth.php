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
        if(!empty($row['product_guid'])) {
            $product=Store::value('SELECT status FROM products WHERE product_guid=?',[Uuid::toBinary($row['product_guid'])]);
            if($product!=='ACTIVE')Http::json(['ok'=>false,'code'=>'SERVICE_PRODUCT_INACTIVE'],403);
        }
        $scopes=json_decode($row['scopes']?:'[]',true)?:[];
        if(!in_array('*',$scopes,true)&&!in_array($scope,$scopes,true)) Http::json(['ok'=>false,'code'=>'SERVICE_SCOPE_DENIED','message'=>'Service scope denied'],403);
        Store::exec('UPDATE service_accounts SET last_used_at=NOW(6) WHERE service_account_guid=?',[Uuid::toBinary($row['service_account_guid'])]);
        return $row;
    }

    public static function checkProduct(array $service,string $productGuid): void
    {
        if(!empty($service['product_guid']) && $service['product_guid']!==$productGuid)
            Http::json(['ok'=>false,'code'=>'SERVICE_PRODUCT_MISMATCH',
                'message'=>'This service account is not authorized for the requested product'],403);
    }

    public static function checkTenant(array $service,string $tenantGuid): array
    {
        try{$bin=Uuid::toBinary($tenantGuid);}
        catch(\InvalidArgumentException){
            Http::json(['ok'=>false,'code'=>'TENANT_INVALID'],400);
        }
        $tenant=Store::one('SELECT pt.product_tenant_guid,pt.product_guid,pt.organisation_guid,pt.status,
            p.status product_status,o.status organisation_status FROM product_tenants pt
            JOIN products p ON p.product_guid=pt.product_guid
            JOIN organisations o ON o.organisation_guid=pt.organisation_guid
            WHERE pt.product_tenant_guid=? LIMIT 1',[$bin]);
        if(!$tenant)Http::json(['ok'=>false,'code'=>'TENANT_NOT_FOUND'],404);
        self::checkProduct($service,$tenant['product_guid']);
        return $tenant;
    }

    public static function checkOrganisation(array $service,string $organisationGuid): void
    {
        if(empty($service['product_guid']))return;
        try{$bin=Uuid::toBinary($organisationGuid);}
        catch(\InvalidArgumentException){Http::json(['ok'=>false,'code'=>'ORGANISATION_INVALID'],400);}
        $allowed=Store::value('SELECT 1 FROM product_tenants pt
            JOIN products p ON p.product_guid=pt.product_guid
            WHERE pt.organisation_guid=? AND pt.product_guid=? AND pt.status="ACTIVE" AND p.status="ACTIVE" LIMIT 1',[
            $bin,Uuid::toBinary($service['product_guid'])
        ]);
        if(!$allowed)Http::json(['ok'=>false,'code'=>'SERVICE_ORGANISATION_DENIED'],403);
    }
}
