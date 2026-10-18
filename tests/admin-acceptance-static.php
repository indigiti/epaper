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
echo "Admin dashboard first-party acceptance/reporting assertions: OK\n";
