<?php
declare(strict_types=1);
/** Syntax-independent contract checks for the ePaper operator console.
 * No database or production configuration is required.
 */
$root=dirname(__DIR__);
$app=(string)file_get_contents($root.'/app/App.php');
$view=(string)file_get_contents($root.'/app/Core/View.php');
$quick=(string)file_get_contents($root.'/app/Admin/QuickOperations.php');
$fail=[];
$check=static function(bool $ok,string $name)use(&$fail):void{
    echo ($ok?'PASS':'FAIL')." - {$name}\n";
    if(!$ok)$fail[]=$name;
};
foreach(['/admin/quick-start','/admin/quick-domain','/admin/quick-domains/','/admin/quick-renew/'] as $path){
    $check(str_contains($app,$path),'authenticated route '.$path);
}
$authPos=strpos($app,'Auth::requireAdmin()');
$csrfPos=strpos($app,'Http::verifyCsrf()');
$startPos=strpos($app,'/admin/quick-start');
$check($authPos!==false&&$csrfPos!==false&&$startPos!==false&&$authPos<$startPos&&$csrfPos<$startPos,'quick operations behind admin auth and csrf');
$check(str_contains($quick,'Store::tx('),'onboarding and renewal use transactions');
$check(str_contains($quick,'normalized_hostname=?'),'domain uniqueness check');
$check(str_contains($quick,"status=\"AWAITING_DNS\""),'new domains require DNS verification');
$check(str_contains($quick,"product_key=\"EPAPER\""),'ePaper product boundary');
$check(str_contains($quick,'FOR UPDATE'),'renewal locks subscription row');
$check(str_contains($quick,'acknowledged'),'renewal requires explicit approval');
$check(str_contains($quick,'billing_state'),'renewal checks billing state');
$check(str_contains($quick,'domain.quick_disable'),'domain disable audit action');
$check(str_contains($view,'data-confirm'),'specific destructive action confirmation');
$check(!str_contains($quick,'$_GET[\'tenant_guid\']'),'no GET tenant authorization bypass');
require $root.'/app/Core/DomainTools.php';
require $root.'/app/Admin/QuickOperations.php';
$method=new ReflectionMethod(\Digiti\Admin\QuickOperations::class,'hostname');
$check($method->invoke(null,'epaper.punemirror.in')==='epaper.punemirror.in','valid customer hostname');
foreach(['platform.digiti.in','https://epaper.punemirror.in','localhost'] as $name){
    try{$method->invoke(null,$name);$blocked=false;}catch(Throwable){$blocked=true;}
    $check($blocked,'reject reserved/invalid hostname '.$name);
}
exit($fail?1:0);
