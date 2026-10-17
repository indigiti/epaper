<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$script=$root.'/bin/build-source-manifest.php';
if(!is_file($script)){fwrite(STDERR,"FAIL: manifest CLI missing\n");exit(1);}
$call=function()use($script):array{
    $proc=@proc_open([PHP_BINARY,$script,'--summary'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($proc))return [];
    fclose($pipes[0]);$json=stream_get_contents($pipes[1]);fclose($pipes[1]);
    fclose($pipes[2]);$exit=proc_close($proc);
    return $exit===0&&is_string($json)?(json_decode($json,true)?:[]):[];
};
$a=$call();$b=$call();
$checks=[
 'manifest returns data'=>!empty($a),
 'manifest deterministic'=>$a===$b,
 'correct schema'=>($a['schema']??'')==='epdi-source-manifest-v1',
 'many tracked files'=>($a['tracked_files']??0)>1000,
 'slim repository size'=>($a['tracked_bytes']??PHP_INT_MAX)<30000000,
 'SHA256 digest'=>preg_match('/^[a-f0-9]{64}$/D',$a['index_digest_sha256']??'')===1,
 'old manifest preserved'=>($a['historical_release_sha256s_replaced']??true)===false,
 'no production-release attestation'=>($a['deployable_artifact_verified']??true)===false,
];
foreach($checks as$name=>$pass)if(!$pass){
 fwrite(STDERR,"FAIL manifest: $name\n");exit(1);
}
echo "PASS deterministic source-only release manifest (".count($checks)." checks)\n";
