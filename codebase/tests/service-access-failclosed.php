<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Services/ServiceAccessPolicy.php';
use EpaperSaas\Services\ServiceAccessPolicy as Policy;
$cases=[
 [['service_access_state'=>'FULL'],'FULL',true],
 [['service_access_state'=>'RESTRICTED'],'RESTRICTED',true],
 [['service_access_state'=>'BLOCKED'],'BLOCKED',false],
 [['service_access_state'=>'SUSPENDED'],'SUSPENDED',false],
 [['service_access_state'=>'CANCELLED'],'CANCELLED',false],
 [['service_access_state'=>'full'],'FULL',true],
 [['service_access_state'=>''],Policy::UNAVAILABLE,false],
 [['service_access_state'=>null],Policy::UNAVAILABLE,false],
 [['ok'=>true],Policy::UNAVAILABLE,false],
 [['service_access_state'=>true],Policy::UNAVAILABLE,false],
 [['service_access_state'=>'FULL<script>'],Policy::UNAVAILABLE,false],
];
foreach($cases as[$response,$expected,$allowed]){
 $actual=Policy::status($response);
 if($actual!==$expected||Policy::permitted($actual)!==$allowed){
  fwrite(STDERR,"FAIL service-access policy\n");exit(1);
 }
}
$app=(string)file_get_contents(dirname(__DIR__).'/app/App.php');
foreach(['ServiceAccessPolicy::UNAVAILABLE','http_response_code(503);',"header('Retry-After: 30');",'Http::privateNoStore();'] as $needle){
 if(!str_contains($app,$needle)){fwrite(STDERR,"FAIL Platform fail-closed marker\n");exit(1);}
}
if(str_contains($app,"catch(\\Throwable){return'FULL';}")){fwrite(STDERR,"FAIL legacy fail-open exception\n");exit(1);}
echo "PASS Platform service-access fail-closed regression (".count($cases)." cases)\n";
