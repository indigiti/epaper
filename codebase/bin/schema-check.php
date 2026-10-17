<?php
declare(strict_types=1);
$root=require dirname(__DIR__).'/app/bootstrap.php';
use EpaperSaas\Services\SchemaDoctor;
try{
    $report=SchemaDoctor::report(true);
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit(!empty($report['ok'])?0:2);
}catch(Throwable $e){
    fwrite(STDERR,json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(1);
}
