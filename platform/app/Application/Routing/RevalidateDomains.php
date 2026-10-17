<?php
declare(strict_types=1);
namespace Digiti\Application\Routing;

use Digiti\Core\{DomainTools,RedisStore,Store,Uuid};
use Digiti\Domain\EdgeProviderFactory;
use Digiti\Application\Integration\CacheInvalidationOutbox;

/** Revalidate DNS outside DB locks; guarded writes and notifications within transaction. */
final class RevalidateDomains
{
    public static function run(int $limit=100):int
    {
        $limit=max(1,min(100,$limit));
        $domains=Store::all('SELECT domain_guid,hostname,verification_token,status,edge_metadata FROM domains WHERE status NOT IN ("SUSPENDED","REMOVED") ORDER BY last_checked_at IS NULL DESC,last_checked_at ASC LIMIT '.$limit);
        $updates=0;
        foreach($domains as $domain){
            $dns=DomainTools::verifyDns($domain['hostname'],$domain['verification_token']);
            $status=$dns['verified']?$domain['status']:'REVALIDATION_FAILED';
            $edge=json_decode($domain['edge_metadata']?:'{}',true)?:[];
            if($dns['verified']&&in_array($domain['status'],['ROUTING_VALID','SSL_PROVISIONING','ACTIVE'],true)){
                try{
                    $edge=EdgeProviderFactory::make()->status($domain['hostname'],$edge);
                    $edgeReady=($edge['status']??'')==='ACTIVE';
                    if($domain['status']==='ACTIVE') {
                        // Do not silently revoke manual-edge approvals on routine DNS rechecks.
                        $status=$edgeReady || ($edge['provider']??null)==='manual'?'ACTIVE':'SSL_PROVISIONING';
                    } else {
                        // Edge readiness never grants activation without administrator approval.
                        $status=($edge['provider']??null)==='manual'?'ROUTING_VALID':'SSL_PROVISIONING';
                    }
                }catch(\Throwable $e){$edge['status_error']=$e->getMessage();}
            }
            $changed=Store::tx(static function()use($domain,$status,$dns,$edge):bool{
                $updated=Store::exec('UPDATE domains SET status=?,dns_metadata=?,edge_metadata=?,last_checked_at=NOW(6),updated_at=NOW(6) WHERE domain_guid=? AND status=? AND status NOT IN ("SUSPENDED","REMOVED")',[
                    $status,json_encode($dns,JSON_THROW_ON_ERROR),json_encode($edge,JSON_THROW_ON_ERROR),
                    Uuid::toBinary($domain['domain_guid']),$domain['status']]);
                if(!$updated)return false;
                if($status!==$domain['status']){
                    $tenant=Store::value('SELECT product_tenant_guid FROM domains WHERE domain_guid=?',[Uuid::toBinary($domain['domain_guid'])]);
                    if(is_string($tenant))CacheInvalidationOutbox::stageForTenant(Uuid::fromBinary($tenant));
                }
                return true;
            });
            if($changed){RedisStore::del('domain:'.$domain['hostname']);$updates++;}
        }
        return $updates;
    }
}
