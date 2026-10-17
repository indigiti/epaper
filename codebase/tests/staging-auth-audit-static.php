<?php
declare(strict_types=1);
$s=(string)file_get_contents(dirname(__DIR__).'/bin/staging-auth-audit.php');
$guard=[
 'target verified by exact allowlist'=>'EPDI_STAGE_APPROVED_HOST',
 'production host disallowed'=>"'epaper.digiti.in'",
 'TLS certificate verification'=>'CURLOPT_SSL_VERIFYPEER=>true',
 'redirect follow disabled'=>'CURLOPT_FOLLOWLOCATION=>false',
 'GET only'=>'CURLOPT_HTTPGET=>true',
 'cookie credentials via private file only'=>'CURLOPT_COOKIEFILE',
 'file permissions'=>'fileperms($real)&0077',
 'authenticated reader A'=>'EPDI_STAGE_READER_A_COOKIE_FILE',
 'authenticated reader B'=>'EPDI_STAGE_READER_B_COOKIE_FILE',
 'five role matrix'=>'PUBLISHER_ADMIN',
 'no-store header check'=>"'no-store'",
 'identity separation markers'=>'reader B excludes reader A marker',
 'incomplete tests do not return success'=>'$incomplete?2:0',
];
foreach($guard as$name=>$needle)if(!str_contains($s,$needle)){
 fwrite(STDERR,"FAIL staging auth static: $name\n");exit(1);
}
echo "PASS authenticated staging harness safety (".count($guard)." assertions)\n";
