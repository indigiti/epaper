<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
 $prefix='Digiti\\';if(!str_starts_with($class,$prefix))return;
 $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
 if(is_file($file))require $file;
});
$stub=new class implements \Digiti\Contracts\DomainActivationRepository{
 public ?array $record=null;
 public int $activations=0;
 public function byGuid(string $id):?array{return $this->record;}
 public function activate(string $id):void{$this->activations++;}
};
$controller=new \Digiti\Controllers\DomainActivationController($stub);
foreach([null,['hostname'=>'x.example','status'=>'PENDING'],['hostname'=>'x.example','status'=>'ACTIVE']] as $record){
 $stub->record=$record;
 try{$controller->activate('guid');throw new RuntimeException('Invalid domain activated');}
 catch(\RuntimeException $e){if(!in_array($e->getMessage(),['Domain not found','Domain is not ready for activation'],true))throw $e;}
}
if($stub->activations!==0)throw new RuntimeException('Invalid activation mutated SQL repository');
foreach(['ROUTING_VALID','SSL_PROVISIONING'] as $ready){
 $stub->record=['hostname'=>'x.example','status'=>$ready];
 if($controller->activate('guid')!=='x.example')throw new RuntimeException('Wrong hostname invalidation target');
}
if($stub->activations!==2)throw new RuntimeException('Expected exactly two valid activation calls');
echo "Platform injected activation policy: OK\n";
