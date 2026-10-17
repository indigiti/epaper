<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Domain/Subscriptions/SubscriptionAccessPolicy.php';
use Digiti\Domain\Subscriptions\SubscriptionAccessPolicy as Policy;
$failed=[];$check=static function(bool $ok,string $name)use(&$failed):void{
    echo ($ok?'PASS':'FAIL')." — ".$name."\n";if(!$ok)$failed[]=$name;
};
$active=[
    'status'=>'ACTIVE','billing_state'=>'ACTIVE','service_access_state'=>'FULL',
    'period_valid'=>1,'tenant_status'=>'ACTIVE','organisation_status'=>'ACTIVE','product_status'=>'ACTIVE'
];
$check(Policy::effective($active)==='FULL','active paid current subscription grants full access');
$check(Policy::effective(null)==='BLOCKED','absent subscription fails closed');
foreach(['tenant_status','organisation_status','product_status'] as $key){
    $r=$active;$r[$key]='SUSPENDED';
    $check(Policy::effective($r)==='BLOCKED',"$key suspension blocks");
}
foreach(['status','billing_state'] as $key)foreach(['PAST_DUE','GRACE_PERIOD'] as $state){
    $r=$active;$r[$key]=$state;
    $check(Policy::effective($r)==='RESTRICTED',"$key $state downgrades full access");
}
foreach(['CANCELLED','EXPIRED'] as $state){
    $r=$active;$r['status']=$state;
    $check(Policy::effective($r)==='BLOCKED',"$state cannot authorize");
}
$r=$active;$r['period_valid']=0;
$check(Policy::effective($r)==='BLOCKED','expired period blocks even if stored FULL');
foreach(['RESTRICTED','BILLING_ONLY','BLOCKED'] as $state){
    $r=$active;$r['service_access_state']=$state;
    $check(Policy::effective($r)===$state,"configured $state never escalated");
}
foreach([['status'=>['ACTIVE','SUSPENDED']],['billing'=>['ACTIVE','UNKNOWN']]] as $matrix){
    foreach($matrix as $label=>$states)foreach($states as $state){
        $list=$label==='status'?Policy::STATUSES:Policy::BILLING;
        try{Policy::allowed($state,$list,$label);$allowed=true;}catch(InvalidArgumentException){$allowed=false;}
        $check($allowed===in_array($state,$list,true),"validates $label state $state");
    }
}
exit($failed?1:0);
