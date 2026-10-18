<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    $prefix='EpaperSaas\\';if(!str_starts_with($class,$prefix))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file))require $file;
});
$fake=new class implements \EpaperSaas\Contracts\AcceptanceMetricsRepository {
    public string $seen='';
    public function collect(string $tenantBinary,bool $aiEnabled):array{
        $this->seen=$tenantBinary;
        return [
            'editions'=>['total'=>2,'published'=>1,'needs_attention'=>0],
            'pages'=>['total'=>6],'clips'=>['total'=>0],
            'jobs'=>['total'=>1,'active'=>0,'failed'=>0],'ai'=>null
        ];
    }
};
$s=new \EpaperSaas\Services\StagingAcceptance($fake);
$input=[
 'status'=>'READY',
 'checks'=>[['category'=>'CORE SERVICES','name'=>'MariaDB','status'=>'READY',
   'detail'=>'Probe OK','action'=>'',
   'metrics'=>['api_key'=>'sensitive'] ]]
];
$report=$s->capture('some-tenant-binary',false,$input);
if($fake->seen!=='some-tenant-binary')throw new RuntimeException('Tenant lookup was not bound');
if($report['automated_status']!=='READY')throw new RuntimeException('Automated readiness lost');
if(empty($report['app_version']))throw new RuntimeException('Report not linked to version');
$features=$report['functional'];
foreach($features as $item){
    if(in_array($item['status'],['PASS','READY','VERIFIED'],true))
        throw new RuntimeException('Observed data falsely marked as tested');
}
if($features[0]['status']!=='OBSERVED'||$features[3]['status']!=='NOT_TESTED'||$features[4]['status']!=='DISABLED')
 throw new RuntimeException('Functional evidence classification changed');
if(str_contains(json_encode($report),'sensitive'))throw new RuntimeException('Secrets from readiness metrics leaked');
$main=(string)file_get_contents($root.'/app/App.php');
if(!str_contains($main,'Auth::requireAdmin();if($method===\'POST\')Http::verifyCsrf();'))
 throw new RuntimeException('Admin CSRF boundary missing');
if(!str_contains($main,'/admin/system/testing'))throw new RuntimeException('QA route is not wired');
$controller=(string)file_get_contents($root.'/app/Controllers/StagingAcceptanceController.php');
if(!str_contains($controller,'Http::privateNoStore()')||!str_contains($controller,'$e(Http::csrf())'))
 throw new RuntimeException('Private QA page or CSRF token missing');
if(!str_contains($controller,"hash('sha256',RequestContext::tenantGuid())"))
 throw new RuntimeException('Reports are not tenant-isolated');
$plan=\EpaperSaas\Services\StagingActionPlan::fromChecks([
 ['name'=>'Redis','status'=>'ACTION_REQUIRED'],
 ['name'=>'RabbitMQ','status'=>'DEGRADED'],
 ['name'=>'Supervisor workers','status'=>'DEGRADED'],
 ['name'=>'Release artifact integrity','status'=>'ACTION_REQUIRED'],
],false);
if(count($plan)!==5 || $plan[0]['priority']!==1 ||
  $plan[2]['status']!=='WAITING_ON_RABBITMQ' ||
  $plan[3]['url']!=='/admin/system/release-audit')
 throw new RuntimeException('Next-action priorities or broker dependency changed');
$healthy=\EpaperSaas\Services\StagingActionPlan::fromChecks([
 ['name'=>'Redis','status'=>'READY'],
 ['name'=>'RabbitMQ','status'=>'READY'],
 ['name'=>'Supervisor workers','status'=>'READY'],
 ['name'=>'Release artifact integrity','status'=>'READY'],
],true);
if(count($healthy)!==1 || $healthy[0]['status']!=='NOT_TESTED')
 throw new RuntimeException('Healthy infrastructure falsely passed functional acceptance');
$production=(string)file_get_contents($root.'/app/Services/ProductionReadiness.php');
if(!str_contains($production,'server-wide maxmemory') ||
   !str_contains($production,'verification_deferred'))
 throw new RuntimeException('Readiness no longer describes shared Redis and deferred broker consumers');
$qaPage=(string)file_get_contents($root.'/app/Controllers/StagingAcceptanceController.php');
if(!str_contains($qaPage,'qa-dashboard') || !str_contains($qaPage,'class="qa-grid"')
 || !str_contains($qaPage,'class="qa-card"')
 || str_contains($qaPage,'class="readiness-row"'))
 throw new RuntimeException('QA page still depends on the 10px readiness-row grid');
$qaStyles=(string)file_get_contents($root.'/public_html/assets/staging-qa.css');
foreach(['.qa-grid','grid-template-columns:repeat(2,minmax(0,1fr))',
          '.qa-card-copy','@media(max-width:620px)','overflow-wrap:anywhere'] as $rule){
 if(!str_contains($qaStyles,$rule))throw new RuntimeException('Missing QA responsive style: '.$rule);
}
if(!str_contains((string)file_get_contents($root.'/app/Core/View.php'),
   "Build::asset('/assets/staging-qa.css')"))
 throw new RuntimeException('QA styling not included in admin layout');
echo "Admin dashboard first-party acceptance/reporting assertions: OK\n";
