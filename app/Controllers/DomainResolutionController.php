<?php
declare(strict_types=1);
namespace Digiti\Controllers;
use Digiti\Contracts\ActiveDomainReader;
use Digiti\Core\{Http,RedisStore};
use Digiti\Domain\ServiceProductScope;
/** Domain resolution HTTP adapter with an injectable, independently testable read port. */
final class DomainResolutionController
{
    public function __construct(private readonly ActiveDomainReader $domains) {}
    /** @param array<string,mixed>|null $cached
        @return array<string,mixed> */
    public function payload(string $host,?array $cached=null,?string $serviceProductGuid=null): array
    {
        if($cached){
            if(!ServiceProductScope::allows($serviceProductGuid,$cached['product_guid']??null))return $this->denied($host);
            return ['ok'=>true,'cached'=>true]+$cached;
        }
        $row=$this->domains->byHostname($host);
        if(!$row || !in_array($row['status'],['ROUTING_VALID','SSL_PROVISIONING','ACTIVE'],true) || !ServiceProductScope::allows($serviceProductGuid,$row['product_guid']??null)){
            return $this->denied($host);
        }
        $data=[
            'domain_guid'=>$row['domain_guid'],'organisation_guid'=>$row['organisation_guid'],
            'product_guid'=>$row['product_guid'],'product_key'=>$row['product_key'],
            'application_guid'=>$row['application_guid'],'application_key'=>$row['application_key'],
            'product_tenant_guid'=>$row['product_tenant_guid'],'hostname'=>$row['hostname'],
            'status'=>$row['status'],'base_url'=>$row['base_url'],
            'default_resource_type'=>$row['default_resource_type'],'default_resource_guid'=>$row['default_resource_guid']
        ];
        return ['ok'=>true,'cached'=>false]+$data;
    }
    public function resolve(string $host,?string $serviceProductGuid=null): never
    {
        $cache=RedisStore::getJson('domain:'.$host);
        $response=$this->payload($host,$cache,$serviceProductGuid);
        if(($response['ok']??false) && !$cache){
            $value=$response;
            unset($value['ok'],$value['cached']);
            RedisStore::setJson('domain:'.$host,$value,600);
        }
        Http::json($response,($response['ok']??false)?200:404);
    }
    /** @return array<string,mixed> */
    private function denied(string $host): array
    {
        // Respond identically for unknown and product-forbidden domains.
        return ['ok'=>false,'code'=>'DOMAIN_NOT_ACTIVE','message'=>'Domain is not active','hostname'=>$host];
    }
}
