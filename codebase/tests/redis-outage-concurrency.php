<?php
declare(strict_types=1);
if(!function_exists('proc_open')){fwrite(STDERR,"FAIL proc_open unavailable; cannot verify cross-process behavior\n");exit(1);}
$base=sys_get_temp_dir().'/epdi-redis-outage-'.bin2hex(random_bytes(7));
$dir=$base.'/config';$rates=$base.'/rate';
mkdir($dir,0700,true);
file_put_contents($dir.'/config.php',"<?php return ".var_export([
 'redis'=>['enabled'=>false,'prefix'=>'staging:test:'],
 'security'=>['rate_limit_storage_dir'=>$rates]
],true).";\n");
$procs=[];$outcomes=[];
try {
    $count=10;$limit=3;$bucket='login:staging:parallel';
    for($i=0;$i<$count;$i++) {
        $args=[PHP_BINARY,dirname(__DIR__).'/tests/fixtures/rate-limit-hit.php',$base,$bucket,(string)$limit];
        $pipes=[];
        $proc=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($proc))throw new \RuntimeException('Worker spawn failed');
        fclose($pipes[0]);
        $procs[]=[$proc,$pipes];
    }
    foreach($procs as[$proc,$pipes]) {
        $stdout=stream_get_contents($pipes[1]);fclose($pipes[1]);
        $stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
        $status=proc_close($proc);
        if($status!==0||!in_array($stdout,['ALLOW','DENY'],true))
            throw new \RuntimeException('Parallel limiter child failed: '.$stderr);
        $outcomes[]=$stdout;
    }
    $allow=count(array_filter($outcomes,fn($s)=>$s==='ALLOW'));
    $deny=count($outcomes)-$allow;
    if($allow!==$limit||$deny!==$count-$limit)
        throw new \RuntimeException("Expected $limit allow/$count total, got $allow allow/$deny deny");
    echo "PASS Redis-offline shared counter under $count simultaneous PHP processes ($allow allowed, $deny denied)\n";
} catch(\Throwable $e) {
    fwrite(STDERR,"FAIL Redis-outage multi-process test: ".$e->getMessage()."\n");
    exit(1);
}finally{
    foreach(glob($rates.'/*.json')?:[] as$file)@unlink($file);
    @rmdir($rates);@unlink($dir.'/config.php');@rmdir($dir);@rmdir($base);
}
