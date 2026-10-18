<?php
declare(strict_types=1);
/** Fail-closed source contract for critical Platform auth and tenant boundaries. */
$root=dirname(__DIR__);
$read=static function(string $path)use($root):string{
    $source=@file_get_contents($root.'/'.$path);
    if($source===false)throw new RuntimeException('Missing Platform source: '.$path);
    return $source;
};
$app=$read('app/App.php');
$internal=$read('app/Controllers/InternalApiActions.php');
$reader=$read('app/Repositories/SqlActiveDomainRepository.php');
$controller=$read('app/Controllers/DomainResolutionController.php');
$crud=$read('app/Admin/Crud.php');
$assert=static function(bool $value,string $name):void {
    if(!$value)throw new RuntimeException('Platform security contract failed: '.$name);
    echo "PASS — {$name}\n";
};
$admin=strpos($app,'Auth::requireAdmin();');
$csrf=strpos($app,"if(\$method==='POST')Http::verifyCsrf();");
$dispatch=strpos($app,'Crud::dispatch($path,$method)');
$assert($admin!==false && $csrf!==false && $dispatch!==false && $admin<$csrf && $csrf<$dispatch,'RBAC and POST CSRF before administration');
$resolve=strpos($internal,"ServiceAuth::requireScope('domains.resolve')");
$handoff=strpos($internal,'DomainResolutionController(new \\Digiti\\Repositories\\SqlActiveDomainRepository())');
$assert($resolve!==false && $handoff!==false && $resolve<$handoff,'service-account authorization precedes domain resolution');
foreach(['p.status="ACTIVE"','a.status="ACTIVE"','pt.status="ACTIVE"','o.status="ACTIVE"','d.normalized_hostname=?'] as $scope) {
    $assert(str_contains($reader,$scope),'domain query guards '.$scope);
}
foreach(["['ROUTING_VALID','SSL_PROVISIONING','ACTIVE']","'code'=>'DOMAIN_NOT_ACTIVE'","'product_tenant_guid'","RedisStore::setJson('domain:"] as $invariant){
    $assert(str_contains($controller,$invariant),'domain endpoint retains '.$invariant);
}
$assert(str_contains($crud,'new SqlOrganisationReader()'),'admin organisation reads through repository');
echo "Platform security boundaries: OK\n";
