<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class) use ($root):void{
 $prefix='EpaperSaas\\';if(!str_starts_with($class,$prefix))return;
 $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
 if(is_file($file))require $file;
});
$stub=new class implements \EpaperSaas\Contracts\VitalsRecorder {
    public array $events=[];
    public function record(array $metrics):void{$this->events[]=$metrics;}
};
$controller=new \EpaperSaas\Controllers\PerformanceVitalsController($stub);
$read=0;$body=static function()use(&$read):array{$read++;return ['metric'=>'LCP'];};
[$code,$response]=$controller->submit(['HTTP_ORIGIN'=>'https://different.example'],'epaper.digiti.in',$body);
if($code!==403||$read!==0||count($stub->events)!==0)throw new RuntimeException('Cross-host origin not rejected before reading body');
[$code,$response]=$controller->submit(['CONTENT_LENGTH'=>'4097'],'epaper.digiti.in',$body);
if($code!==413||$read!==0)throw new RuntimeException('Oversize body not rejected before reading body');
[$code,$response]=$controller->submit(['HTTP_ORIGIN'=>'https://EPAPER.DIGITI.IN','CONTENT_LENGTH'=>'4096'],'epaper.digiti.in',$body);
if($code!==202||$response!==['ok'=>true]||$read!==1||count($stub->events)!==1)throw new RuntimeException('Valid Web Vitals not recorded');
echo "ePaper injected HTTP controller assertions: OK\n";
