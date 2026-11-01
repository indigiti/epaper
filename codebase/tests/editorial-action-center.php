<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
 $prefix='EpaperSaas\\';if(!str_starts_with($class,$prefix))return;
 $f=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
 if(is_file($f))require $f;
});
$guid='01234567-89ab-4cde-8fab-0123456789ab';
$repo=new class($guid) implements \EpaperSaas\Contracts\EditorialActionRepository{
 public array $calls=[];public bool $failAi=false;
 public function __construct(private string $guid){}
 public function editions(string $tenantBinary,string $status,int $limit):array{
  $this->calls[]=['editions',$tenantBinary,$status,$limit];
  return [['edition_guid'=>$this->guid,'title'=>'<script>bad</script>',
    'edition_date'=>'2026-10-18','publication_name'=>'Daily']];
 }
 public function failedJobs(string $tenantBinary,int $limit):array{
  $this->calls[]=['jobs',$tenantBinary,$limit];
  return [['edition_guid'=>$this->guid,'job_type'=>'PAGE_OCR',
    'edition_date'=>'2026-10-18','title'=>'Daily']];
 }
 public function pendingAi(string $tenantBinary,int $limit):array{
  $this->calls[]=['ai',$tenantBinary,$limit];
  if($this->failAi)throw new RuntimeException('secret SQL error');
  return ['total'=>3,'rows'=>[['page_guid'=>$this->guid,
   'title'=>'Daily','page_number'=>2]]];
 }
};
$svc=new \EpaperSaas\Services\EditorialActionCenter($repo);
$operator=$svc->snapshot('tenant-binary',['NEEDS_ATTENTION'=>5],'OPERATOR',true);
if(count($operator['groups'])!==1||$repo->calls[0][0]!=='jobs')
 throw new RuntimeException('Operator role must not query editor queues');
$repo->calls=[];
$editor=$svc->snapshot('tenant-binary',['NEEDS_ATTENTION'=>5],'EDITOR',true);
if(count($editor['groups'])!==4||$editor['pending_count']!==8||count($repo->calls)!==4)
 throw new RuntimeException('Editor queues or pending counts wrong');
foreach($repo->calls as $call){
 if($call[1]!=='tenant-binary'||end($call)!==4)
  throw new RuntimeException('Tenant bound or bounded list contract violated');
}
$html=\EpaperSaas\Services\EditorialActionView::render($editor);
if(str_contains($html,'<script>')||!str_contains($html,'&lt;script&gt;'))
 throw new RuntimeException('Injected HTML in title');
foreach(['/admin/processing/'.$guid,'/admin/ai-review/'.$guid,'/admin/editions?date=2026-10-18'] as $route)
 if(!str_contains($html,$route))throw new RuntimeException('Action URL missing '.$route);
$repo->calls=[];
$disabled=$svc->snapshot('tenant-binary',[],'EDITOR',false);
if(count($repo->calls)!==3||$disabled['groups'][3]['status']!=='DISABLED')
 throw new RuntimeException('Disabled AI should never query optional table');
$repo->failAi=true;$error=$svc->snapshot('tenant-binary',[],'EDITOR',true);
if($error['groups'][3]['status']!=='UNAVAILABLE'||str_contains(json_encode($error),'secret SQL error'))
 throw new RuntimeException('AI table failures must be visible but non-sensitive');
$sql=(string)file_get_contents($root.'/app/Repositories/SqlEditorialActionRepository.php');
foreach(['e.tenant_guid=?','j.tenant_guid=?','c.tenant_guid=?','LIMIT ','c.status="PENDING"','e.publish_mode="MANUAL"'] as $guard)
 if(!str_contains($sql,$guard))throw new RuntimeException('SQL boundary missing '.$guard);
$app=(string)file_get_contents($root.'/app/App.php');
if(!str_contains($app,'EditorialActionView::render($editorial)'))
 throw new RuntimeException('Editorial center not connected to dashboard');
$styles=(string)file_get_contents($root.'/public_html/assets/editorial-actions.css');
if(!str_contains($styles,'grid-template-columns:repeat(2,minmax(0,1fr))')
 || !str_contains($styles,'@media(max-width:620px)'))
 throw new RuntimeException('Editorial CSS responsive grid not found');
echo "Editorial Action Center role/tenant/UI/source contracts: OK\n";
