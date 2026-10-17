<?php
declare(strict_types=1);
namespace Digiti\Application\Integration;

use Digiti\Core\{Config,Store,Uuid};

/** Call stageForTenant within the caller's existing database transaction. */
final class CacheInvalidationOutbox
{
    public static function stageForTenant(string $tenantGuid): array
    {
        if(!Uuid::isValid($tenantGuid))throw new \InvalidArgumentException('Invalid tenant ID');
        // Only EPAPER data-plane apps receive these events; never broadcast across products.
        $product=Store::value('SELECT p.product_key FROM product_tenants t JOIN products p ON p.product_guid=t.product_guid WHERE t.product_tenant_guid=?',[Uuid::toBinary($tenantGuid)]);
        if($product!=='EPAPER')return [];
        $rows=Store::all('SELECT hostname FROM domains WHERE product_tenant_guid=? ORDER BY hostname',[Uuid::toBinary($tenantGuid)]);
        $hosts=array_column($rows,'hostname');
        // Chunk for small, bounded signed payloads; even a hostless tenant needs access revocation.
        $chunks=array_chunk($hosts,32);
        if(!$chunks)$chunks=[[]];
        $created=[];
        foreach($chunks as $chunk){
            $event=Uuid::v7();
            $payload=['event_id'=>$event,'tenant_guid'=>$tenantGuid,'hostnames'=>$chunk,'kind'=>'control_plane.changed'];
            $created[]=$event;
            Store::exec('INSERT INTO outbox_events(outbox_event_guid,tenant_guid,event_type,aggregate_type,aggregate_guid,payload,status,available_at,created_at) VALUES(?,?,"cache.invalidate","product_tenant",?,?, "PENDING",NOW(6),NOW(6))',[
                Uuid::toBinary($event),Uuid::toBinary($tenantGuid),Uuid::toBinary($tenantGuid),
                json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)
            ]);
        }
        return $created;
    }

    /** Stage every ePaper tenant touched by an organisation, product or application. */
    public static function stageForParent(string $column,string $guid): void
    {
        if(!in_array($column,['organisation_guid','product_guid','application_guid'],true)||!Uuid::isValid($guid))
            throw new \InvalidArgumentException('Unsupported invalidation scope');
        if($column==='application_guid')
            $rows=Store::all('SELECT DISTINCT pt.product_tenant_guid FROM domains d JOIN product_tenants pt ON pt.product_tenant_guid=d.product_tenant_guid WHERE d.application_guid=?',[Uuid::toBinary($guid)]);
        else $rows=Store::all('SELECT product_tenant_guid FROM product_tenants WHERE '.$column.'=?',[Uuid::toBinary($guid)]);
        foreach($rows as $row)self::stageForTenant($row['product_tenant_guid']);
    }
}
