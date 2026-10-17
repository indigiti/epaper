<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Domain/Metering/UsagePolicy.php';
use Digiti\Domain\Metering\UsagePolicy as Policy;
$fail=[];$check=static function(bool $ok,string $name)use(&$fail):void{
    echo ($ok?'PASS':'FAIL')." — ".$name."\n";if(!$ok)$fail[]=$name;
};
foreach([['1','1'],['0.0001','0.0001'],['-1.25','-1.25'],[1.5,'1.5000'],[0,'0.0000']] as [$input,$out])
    $check(Policy::delta($input)===$out,"valid bounded usage $out");
foreach([INF,NAN,0.00001,'1e10','1000000000','0.00001','NaN',[],null,'-9999999999'] as $input) {
    try{Policy::delta($input);$reject=false;}catch(InvalidArgumentException){$reject=true;}
    $check($reject,'invalid/unsafe metering quantity denied');
}
foreach(['2026-01','2026-12'] as $valid)
    $check(Policy::period($valid)===$valid,"valid period $valid");
foreach(['2026-00','2026-13','../../','2026-1','2026-12;DROP'] as $invalid){
    try{Policy::period($invalid);$reject=false;}catch(InvalidArgumentException){$reject=true;}
    $check($reject,'invalid metering period denied');
}
exit($fail?1:0);
