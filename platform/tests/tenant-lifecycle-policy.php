<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Domain/Tenants/TenantLifecyclePolicy.php';
use Digiti\Domain\Tenants\TenantLifecyclePolicy as Policy;
$errors=[];$check=static function(bool $ok,string $label)use(&$errors):void{
    echo ($ok?'PASS':'FAIL')." — ".$label."\n";if(!$ok)$errors[]=$label;
};
foreach([['ACTIVE','SUSPENDED'],['SUSPENDED','ACTIVE'],['ACTIVE','REMOVED'],['REMOVED','REMOVED']] as [$old,$new])
    $check(Policy::transition($old,$new)===$new,"allowed transition $old to $new");
foreach([['REMOVED','ACTIVE'],['REMOVED','SUSPENDED'],['ACTIVE','UNKNOWN']] as [$old,$new]){
    try{Policy::transition($old,$new);$denied=false;}
    catch(\InvalidArgumentException|\DomainException){$denied=true;}
    $check($denied,"illegal transition $old to $new denied");
}
exit($errors?1:0);
