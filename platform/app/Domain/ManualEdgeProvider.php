<?php
namespace Digiti\Domain;
final class ManualEdgeProvider implements EdgeProviderInterface
{
    public function key():string{return'manual';}
    public function provision(string $hostname,array $existing=[]):array{return['provider'=>'manual','status'=>'ROUTING_VALID','hostname'=>$hostname,'instructions'=>'Provision TLS/hostname at the edge, then activate the domain in Platform Admin.'];}
    public function status(string $hostname,array $metadata=[]):array{return array_merge($metadata,['provider'=>'manual','status'=>$metadata['status']??'ROUTING_VALID','hostname'=>$hostname]);}
}
