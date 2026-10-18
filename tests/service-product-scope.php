<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
 $prefix='Digiti\\';if(!str_starts_with($class,$prefix))return;
 $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
 if(is_file($file))require $file;
});
$policy=\Digiti\Domain\ServiceProductScope::class;
if(!$policy::allows(null,'other') || !$policy::allows('PRODUCT-A','product-a')
 || $policy::allows('product-a','product-b') || $policy::allows('product-a',null)){
 throw new RuntimeException('Product-scoped service account isolation failed');
}
$stub=new class implements \Digiti\Contracts\ActiveDomainReader {
 public int $reads=0;
 public function byHostname(string $host):?array{
   $this->reads++;
   return ['domain_guid'=>'d','organisation_guid'=>'o','product_guid'=>'prod-a',
    'product_key'=>'EPAPER','application_guid'=>'a','application_key'=>'city_epaper',
    'product_tenant_guid'=>'t','hostname'=>$host,'status'=>'ACTIVE','base_url'=>'https://epaper.digiti.in',
    'default_resource_type'=>null,'default_resource_guid'=>null];
 }
};
$controller=new \Digiti\Controllers\DomainResolutionController($stub);
$r=$controller->payload('site.example',null,'prod-b');
if(($r['ok']??true)!==false||$r['code']!=='DOMAIN_NOT_ACTIVE')throw new RuntimeException('Cross-product domain read leaked');
$r=$controller->payload('site.example',null,'prod-a');
if(($r['ok']??false)!==true)throw new RuntimeException('Same-product domain access incorrectly denied');
$reads=$stub->reads;
$r=$controller->payload('site.example',['product_guid'=>'prod-b','status'=>'ACTIVE'],'prod-a');
if(($r['ok']??true)!==false||$stub->reads!==$reads)throw new RuntimeException('Cached foreign product leaked');
echo "Product-bound service account guards: OK\n";
