<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class) use ($root): void {
    $prefix='Digiti\\';
    if(!str_starts_with($class,$prefix))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file))require $file;
});
$ref=new ReflectionClass(\Digiti\App::class);
$methods=array_map(static fn(ReflectionMethod $m): string=>$m->name,$ref->getMethods());
if(count($methods)!==45)throw new RuntimeException('Expected 45 existing Platform methods, got '.count($methods));
foreach(['run','dashboard','domains','verifyDomain','activateDomain','resolveDomain','authToken','subscriptions','webhook'] as $name){
    if(!in_array($name,$methods,true))throw new RuntimeException('Missing Platform action '.$name);
}
foreach(['AdminActions','InternalApiActions','SupportActions'] as $short){
    if(!trait_exists('Digiti\\Controllers\\'.$short))throw new RuntimeException('Missing controller trait '.$short);
}
$adapter=new \Digiti\Repositories\SqlOrganisationReader();
if(!$adapter instanceof \Digiti\Contracts\OrganisationReader)throw new RuntimeException('Organisation reader contract invalid');
echo "Platform extracted actions and organisation repository contract: OK\n";
