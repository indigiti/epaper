<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class) use ($root):void{
 $prefix='Digiti\\';if(!str_starts_with($class,$prefix))return;
 $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
 if(is_file($file))require $file;
});
$stub=new class implements \Digiti\Contracts\ActiveDomainReader {
 public int $calls=0;
 public ?array $row=null;
 public function byHostname(string $normalizedHostname):?array{$this->calls++;return $this->row;}
};
$c=new \Digiti\Controllers\DomainResolutionController($stub);
$r=$c->payload('unregistered.example');
if($r!==['ok'=>false,'code'=>'DOMAIN_NOT_ACTIVE','message'=>'Domain is not active','hostname'=>'unregistered.example'])throw new RuntimeException('Inactive domain response changed');
$stub->row=['status'=>'SUSPENDED'];
if(($c->payload('suspended.example')['ok']??null)!==false)throw new RuntimeException('Suspended domain was resolved');
$stub->row=['domain_guid'=>'d','organisation_guid'=>'o','product_guid'=>'p','product_key'=>'epaper','application_guid'=>'a','application_key'=>'city_epaper','product_tenant_guid'=>'t','hostname'=>'paper.example','status'=>'ACTIVE','base_url'=>'https://epaper.digiti.in','default_resource_type'=>null,'default_resource_guid'=>null];
$r=$c->payload('paper.example');
if(($r['ok']??false)!==true||($r['cached']??true)!==false||$r['product_tenant_guid']!=='t')throw new RuntimeException('Active domain lookup parity changed');
$before=$stub->calls;
$r=$c->payload('paper.example',['hostname'=>'paper.example','status'=>'ACTIVE']);
if(($r['cached']??false)!==true||$stub->calls!==$before)throw new RuntimeException('Cache hit incorrectly queried database');
echo "Platform injected domain controller assertions: OK\n";
