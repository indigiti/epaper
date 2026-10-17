<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Domain/Billing/Money.php';
use Digiti\Domain\Billing\Money;
$failed=[];
$check=static function(bool $ok,string $label)use(&$failed):void{echo ($ok?'PASS':'FAIL')." — ".$label."\n";if(!$ok)$failed[]=$label;};
foreach(['0.01'=>1,'1'=>100,'1.5'=>150,'1.05'=>105,'1999.99'=>199999,'9999999999.99'=>999999999999] as $input=>$expected)
    $check(Money::inrMinor((string)$input)===$expected,"precise amount $input");
foreach(['','0','0.00','-1','1.000','1.999','1e3','0x10','1,000','01.00','99999999999','abc','NaN'] as $input){
    try{Money::inrMinor($input);$ok=false;}
    catch(InvalidArgumentException){$ok=true;}
    $check($ok,"invalid money rejected: $input");
}
exit($failed?1:0);
