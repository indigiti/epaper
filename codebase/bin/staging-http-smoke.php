<?php
declare(strict_types=1);
/**
 * Non-destructive staging HTTP smoke test.
 *
 * Usage: php bin/staging-http-smoke.php --base-url=https://staging.example.invalid
 * No credentials, cookies, request bodies, redirects or mutating HTTP methods.
 */
$args=$argv??[];
$base='';
foreach($args as $arg)if(str_starts_with($arg,'--base-url='))$base=rtrim(substr($arg,11),'/');
$parts=$base!==''?parse_url($base):false;
$host=is_array($parts)?strtolower((string)($parts['host']??'')):'';
if(!is_array($parts)
    || ($parts['scheme']??'')!=='https'
    || $host===''
    || $host==='epaper.digiti.in'
    || isset($parts['user'])||isset($parts['pass'])
    || isset($parts['query'])||isset($parts['fragment'])
    || !function_exists('curl_init')){
    fwrite(STDERR,"Usage: php bin/staging-http-smoke.php --base-url=https://your-staging-domain\n");
    fwrite(STDERR,"A non-production HTTPS staging endpoint and cURL are required.\n");
    exit(2);
}
$checks=[
 ['/health/live','health',200],
 ['/health/ready','readiness',200],
 ['/robots.txt','robots',200],
 ['/install.php','installed',403],
 ['/admin','anonymous_admin',302],
];
$fails=0;$results=[];
foreach($checks as[$path,$label,$wanted]) {
    $ch=curl_init($base.$path);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_TIMEOUT=>12,
        CURLOPT_CONNECTTIMEOUT=>4,
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_USERAGENT=>'EP-DI-staging-smoke/1.0',
        CURLOPT_HTTPGET=>true,
        CURLOPT_HTTPHEADER=>['Accept: application/json,text/html;q=0.8','Cache-Control: no-cache'],
    ]);
    $body=curl_exec($ch);
    $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    $err=curl_error($ch);
    curl_close($ch);
    $ok=$body!==false&&$status===$wanted;
    if($ok&&in_array($label,['health','readiness'],true)){
        $json=json_decode((string)$body,true);
        $ok=is_array($json)&&($json['ok']??false)===true;
    }
    $results[]=['name'=>$label,'ok'=>$ok,'status'=>$status,'expected'=>$wanted];
    if(!$ok){$fails++;fwrite(STDERR,"FAIL $label HTTP $status expected $wanted".($err!==''?' (transport error)':'')."\n");}
}
echo json_encode(['ok'=>$fails===0,'checks'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($fails?1:0);
