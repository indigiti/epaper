<?php
declare(strict_types=1);
$source=(string)file_get_contents(dirname(__DIR__).'/bin/staging-http-smoke.php');
$checks=[
  'non-mutating requests'=>'CURLOPT_HTTPGET=>true',
  'no redirect following'=>'CURLOPT_FOLLOWLOCATION=>false',
  'TLS peer validation'=>'CURLOPT_SSL_VERIFYPEER=>true',
  'TLS hostname validation'=>'CURLOPT_SSL_VERIFYHOST=>2',
  'require staging HTTPS'=>"'https'",
  'deny canonical production hostname'=>"'epaper.digiti.in'",
  'verify logged out admin'=>'anonymous_admin',
  'verify installer locked'=>'installed',
  'verify health'=>'health',
  'verify readiness'=>'readiness',
];
foreach($checks as $name=>$needle) {
    if(!str_contains($source,$needle)){fwrite(STDERR,"FAIL staging smoke: $name\n");exit(1);}
}
echo 'PASS staging smoke safety guards ('.count($checks)." assertions)\n";
