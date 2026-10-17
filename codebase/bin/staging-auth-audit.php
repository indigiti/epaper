<?php
declare(strict_types=1);
/**
 * Safe, read-only authenticated staging acceptance. Never paste credentials
 * into GitHub. Use ephemeral cookie jars with chmod 0600.
 *
 * EPDI_STAGE_BASE_URL=https://staging-host
 * EPDI_STAGE_APPROVED_HOST=staging-host
 * EPDI_STAGE_READER_A_COOKIE_FILE=/private/reader-a.cookies
 * EPDI_STAGE_READER_B_COOKIE_FILE=/private/reader-b.cookies
 * EPDI_STAGE_ROLE_{OWNER,ADMIN,PUBLISHER_ADMIN,EDITOR,OPERATOR}_COOKIE_FILE
 * EPDI_STAGE_READER_A_MARKER / EPDI_STAGE_READER_B_MARKER (distinct visible text)
 */
if(!function_exists('curl_init')){fwrite(STDERR,"cURL is required\n");exit(2);}
$base=rtrim((string)(getenv('EPDI_STAGE_BASE_URL')?:''),'/');
$parts=parse_url($base);
$host=is_array($parts)?strtolower((string)($parts['host']??'')):'';
$approved=strtolower(trim((string)(getenv('EPDI_STAGE_APPROVED_HOST')?:'')));
if(!is_array($parts)||($parts['scheme']??'')!=='https'||$host===''||$host!==$approved
  ||$host==='epaper.digiti.in'||isset($parts['user'])||isset($parts['pass'])
  ||isset($parts['query'])||isset($parts['fragment'])
  ||(!empty($parts['path'])&&$parts['path']!=='/')){
  fwrite(STDERR,"Set approved non-production EPDI_STAGE_BASE_URL and matching EPDI_STAGE_APPROVED_HOST (HTTPS only).\n");
  exit(2);
}
function jar(string $name): ?string {
    $file=trim((string)(getenv($name)?:''));
    if($file==='')return null;
    $real=realpath($file);
    if($real===false||!is_file($real)||!is_readable($real)||(fileperms($real)&0077)!==0){
        throw new RuntimeException("Missing/non-private cookie jar for $name (permissions 0600 required)");
    }
    return $real;
}
function request(string $base,string $uri,?string $cookie): array {
    $headers=[];
    $ch=curl_init($base.$uri);
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_FOLLOWLOCATION=>false,
      CURLOPT_CONNECTTIMEOUT=>4,
      CURLOPT_TIMEOUT=>15,
      CURLOPT_SSL_VERIFYPEER=>true,
      CURLOPT_SSL_VERIFYHOST=>2,
      CURLOPT_HTTPGET=>true,
      CURLOPT_USERAGENT=>'EPDI-ReadOnly-Staging-Audit/1.0',
      CURLOPT_HTTPHEADER=>['Cache-Control: no-cache','Pragma: no-cache'],
      CURLOPT_HEADERFUNCTION=>static function($h,$line)use(&$headers){
         $pos=strpos($line,':');
         if($pos!==false){$name=strtolower(trim(substr($line,0,$pos)));$headers[$name]=trim(substr($line,$pos+1));}
         return strlen($line);
      }
    ]);
    if($cookie!==null)curl_setopt($ch,CURLOPT_COOKIEFILE,$cookie);
    $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    $error=curl_errno($ch);
    curl_close($ch);
    return ['status'=>$status,'headers'=>$headers,'body'=>is_string($body)?$body:'','network_ok'=>$error===0&&$body!==false];
}
$tests=[];$failed=0;$incomplete=0;$home=[];
$check=static function(string $name,bool $ok,bool $required=true)use(&$tests,&$failed):void {
    $tests[]=['name'=>$name,'pass'=>$ok,'required'=>$required];
    if(!$ok&&$required)$failed++;
};
try{
    $a=jar('EPDI_STAGE_READER_A_COOKIE_FILE');
    $b=jar('EPDI_STAGE_READER_B_COOKIE_FILE');
    if($a===null||$b===null||$a===$b)throw new RuntimeException('Two separate authenticated reader cookie files are mandatory');
    $markerA=(string)(getenv('EPDI_STAGE_READER_A_MARKER')?:'');
    $markerB=(string)(getenv('EPDI_STAGE_READER_B_MARKER')?:'');
    $markers=trim($markerA)!==''&&trim($markerB)!==''&&$markerA!==$markerB;
    if(!$markers)$incomplete++;
    foreach(['a'=>$a,'b'=>$b] as$who=>$cookie){
        $bootstrap=request($base,'/api/v1/auth/mobile/bootstrap',$cookie);
        $json=json_decode($bootstrap['body'],true);
        $check("reader_$who identity authenticated",$bootstrap['network_ok']&&$bootstrap['status']===200
            &&is_array($json)&&($json['authenticated']??false)===true);
        foreach(['/','/archive','/search'] as$uri){
            $resp=request($base,$uri,$cookie);
            $header=strtolower((string)($resp['headers']['cache-control']??''));
            $check("reader_$who private no-store $uri",$resp['network_ok']&&$resp['status']===200
                &&str_contains($header,'private')&&str_contains($header,'no-store'));
            $check("reader_$who no shared cache age $uri",!isset($resp['headers']['age'])||(int)$resp['headers']['age']===0);
            if($uri==='/')$home[$who]=$resp['body'];
        }
    }
    if($markers){
        $check('reader A own identity marker',str_contains((string)($home['a']??''),$markerA));
        $check('reader B own identity marker',str_contains((string)($home['b']??''),$markerB));
        $check('reader B excludes reader A marker',!str_contains((string)($home['b']??''),$markerA));
        $check('reader A excludes reader B marker',!str_contains((string)($home['a']??''),$markerB));
    }
    $anon=request($base,'/admin',null);
    $check('anonymous admin denied or redirected',in_array($anon['status'],[302,303,401,403],true));

    $roles=['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','OPERATOR'];
    $roleChecks=[
      'OWNER'=>[['/admin',200],['/admin/settings/appearance',200]],
      'ADMIN'=>[['/admin',200],['/admin/settings/appearance',200]],
      'PUBLISHER_ADMIN'=>[['/admin',200],['/admin/system/infrastructure',403]],
      'EDITOR'=>[['/admin',200],['/admin/articles',200],['/admin/settings/appearance',403],['/admin/system/infrastructure',403]],
      'OPERATOR'=>[['/admin',200],['/admin/processing',200],['/admin/articles',403],['/admin/settings/appearance',403]],
    ];
    foreach($roles as$role) {
        $cookie=jar('EPDI_STAGE_ROLE_'.$role.'_COOKIE_FILE');
        if($cookie===null){$incomplete++;continue;}
        foreach($roleChecks[$role] as[$uri,$status]){
            $response=request($base,$uri,$cookie);
            $check("$role $uri=$status",$response['network_ok']&&$response['status']===$status);
        }
    }
} catch(Throwable $e) {
    // Never print cookies, identities, response bodies or full target URLs.
    fwrite(STDERR,"Staging test setup failed: ".$e->getMessage()."\n");
    exit(2);
}
$summary=['ok'=>$failed===0&&$incomplete===0,'complete'=>$incomplete===0,
  'checks'=>count($tests),'failed'=>$failed,'missing_coverage_items'=>$incomplete,
  'results'=>$tests,'note'=>'No live secrets, raw cookies or response bodies included'];
echo json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
exit($failed?1:($incomplete?2:0));
