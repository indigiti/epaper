<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
 $prefix='Digiti\\';if(!str_starts_with($class,$prefix))return;
 $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
 if(is_file($file))require $file;
});
$reader=new class implements \Digiti\Contracts\AdminDashboardReader {
 public int $calls=0;
 public function counts():array{
  $this->calls++;
  return ['Organisations'=>1,'Product Tenants'=>2,'Active Domains'=>3,'Subscriptions'=>4];
 }
};
$controller=new \Digiti\Controllers\AdminDashboardController($reader);
$actual=$controller->counts();
if($reader->calls!==1 || $actual!==['Organisations'=>1,'Product Tenants'=>2,'Active Domains'=>3,'Subscriptions'=>4])
 throw new RuntimeException('Platform dashboard repository/controller parity');
echo "Platform independent admin controller and repository contract: OK\n";
