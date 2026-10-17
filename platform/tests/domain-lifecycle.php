<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Domain/Routing/DomainLifecyclePolicy.php';
use Digiti\Domain\Routing\DomainLifecyclePolicy as Policy;
$fail=[];
$check=static function(bool $ok,string $label)use(&$fail):void{echo ($ok?'PASS':'FAIL')." — $label\n";if(!$ok)$fail[]=$label;};
foreach([['ACTIVE','SUSPENDED'],['ACTIVE','ACTIVE'],['SUSPENDED','SUSPENDED'],['REMOVED','REMOVED']] as [$from,$to])
    $check(Policy::editableStatus($from,$to)===$to,"allowed domain status $from to $to");
foreach([['ACTIVE','REMOVED'],['SUSPENDED','ACTIVE'],['REMOVED','ACTIVE'],['REMOVED','SUSPENDED'],['ROUTING_VALID','ACTIVE']] as [$from,$to]){
    try{Policy::editableStatus($from,$to);$rejected=false;}
    catch(DomainException){$rejected=true;}
    $check($rejected,"domain edit rejects $from to $to");
}
$check(Policy::canActivate('ROUTING_VALID',['provider'=>'manual','status'=>'ROUTING_VALID']),'manual edge must be explicitly verified before activation');
$check(Policy::canActivate('SSL_PROVISIONING',['provider'=>'cloudflare','status'=>'ACTIVE']),'Cloudflare edge and certificate ready for manual approval');
foreach([
    ['AWAITING_DNS',['provider'=>'manual','status'=>'ROUTING_VALID']],
    ['REMOVED',['provider'=>'cloudflare','status'=>'ACTIVE']],
    ['SUSPENDED',['provider'=>'manual','status'=>'ROUTING_VALID']],
    ['ROUTING_VALID',['provider'=>'cloudflare','status'=>'ACTIVE']],
    ['SSL_PROVISIONING',['provider'=>'cloudflare','status'=>'SSL_PROVISIONING']],
    ['SSL_PROVISIONING',['provider'=>'cloudflare','status'=>'ROUTING_VALID']],
    ['ROUTING_VALID',['provider_error'=>'Timeout']]
] as [$status,$edge])
    $check(!Policy::canActivate($status,$edge),"unready edge cannot activate $status");
exit($fail?1:0);
