<?php
/** No database/bootstrap required: compile-check all extracted controllers. */
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class) use ($root): void {
    $prefix='EpaperSaas\\';
    if(!str_starts_with($class,$prefix))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file))require $file;
});
$ref=new ReflectionClass(\EpaperSaas\App::class);
$methods=array_map(static fn(ReflectionMethod $m): string=>$m->name,$ref->getMethods());
if(count($methods)!==200)throw new RuntimeException('Expected 200 existing route/utility methods, got '.count($methods));
foreach(['run','adminPublish','adminEditions','reader','apiClip','publicClip','runAdmin','runAccount','apiSearch','healthReady'] as $name){
    if(!in_array($name,$methods,true))throw new RuntimeException('Missing route action '.$name);
}
foreach(['PublishingActions','ReaderActions','ClippingActions','AdministrationActions','ApiActions','AccountActions','OperationsActions','SupportActions'] as $short){
    if(!trait_exists('EpaperSaas\\Controllers\\'.$short))throw new RuntimeException('Missing controller trait '.$short);
}
$adapter=new \EpaperSaas\Repositories\SqlPublishingMetricsRepository();
if(!$adapter instanceof \EpaperSaas\Contracts\PublishingMetricsRepository){
    throw new RuntimeException('Publishing metrics repository contract invalid');
}
echo "ePaper extracted route actions and repository contracts: OK\n";
