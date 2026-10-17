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
exit($fail?1:0);
