<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/Core/View.php';
require_once $root.'/app/Admin/Onboarding.php';
require_once $root.'/app/Admin/OnboardingDashboard.php';
require_once $root.'/app/Admin/DomainMapping.php';

function check(bool $condition,string $message): void {
    if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}

$menu=(new ReflectionMethod(\Digiti\Core\View::class,'menu'))->invoke(null,'/admin');
check(str_contains($menu,'Publishers &amp; domains'),'task-first publisher navigation');
check(str_contains($menu,'Team &amp; access'),'plain-language staff navigation');
check(str_contains($menu,'Advanced administration'),'separate advanced administration');
check(str_contains($menu,'aria-current="page"'),'current navigation link announced');
check(str_contains($menu,'/admin/help'),'help navigation exists');

$domain=[
    'domain_guid'=>'01a05e76-d968-7e72-a4ef-aec784cf0547',
    'hostname'=>'epaper.dcnnews.co.in',
    'organisation_status'=>'ACTIVE',
    'tenant_status'=>'ACTIVE',
    'application_status'=>'ACTIVE',
    'owner_count'=>1,
    'service_access_state'=>'FULL',
    'verified_at'=>'2026-11-01 12:00:00',
    'edge_metadata'=>'{}',
    'updated_at'=>date('Y-m-d H:i:s'),
    'status'=>'ROUTING_VALID',
    'reviewed'=>false,
];
$partial=\Digiti\Admin\Onboarding::state($domain);
check($partial['done']===4 && !$partial['live'],'routing valid is not approved go-live');
$domain['reviewed']=true;
$domain['status']='ACTIVE';
$complete=\Digiti\Admin\Onboarding::state($domain);
check($complete['done']===6 && $complete['live'],'operator-reviewed ACTIVE site is counted as complete');

$stage=(new ReflectionMethod(\Digiti\Admin\OnboardingDashboard::class,'stage'));
$domain['_shares_default']=true;
$shared=$stage->invoke(null,$domain,$complete);
check($shared['class']==='shared','shared-content hostname is highlighted despite upstream ACTIVE');
$domain['_shares_default']=false;
$canonical=$stage->invoke(null,$domain,$complete);
check($canonical['class']==='live','separate tenant with valid review is approved');

$view=file_get_contents($root.'/app/Core/View.php');
$home=file_get_contents($root.'/app/Admin/Home.php');
$help=file_get_contents($root.'/app/Admin/Help.php');
$app=file_get_contents($root.'/app/App.php');
$onboard=file_get_contents($root.'/app/Admin/Onboarding.php');
$panel=file_get_contents($root.'/app/Admin/OnboardingDashboard.php');
$error=file_get_contents($root.'/app/Core/ErrorCenter.php');
$css=file_get_contents($root.'/public_html/assets/platform-ui.css');
check(str_contains($view,'platform-ui.css?v=')&&str_contains($view,"hash_file('sha256'"),'CSS content fingerprinting');
check(str_contains($app,'Home::index();')&&str_contains($app,'Help::index();'),'simple pages are routed');
check(str_contains($app,'Existing team members')&&str_contains($app,'platform-form-disclosure'),'existing staff come before the optional add form');
check(str_contains($app,'Add team member</button></div></form></div></details>'),'team creation disclosure uses valid closing tags');

$mapping=file_get_contents($root.'/app/Admin/DomainMapping.php');
$crud=file_get_contents($root.'/app/Admin/Crud.php');
check(str_contains($app,'DomainMapping::save('),'new domain-mapping POST endpoint is available');
check(str_contains($crud,'DomainMapping::panel($r)'),'domain editor displays the dropdown workflow');
check(str_contains($mapping,'name="product_tenant_guid"')&&str_contains($mapping,'name="application_guid"'),'both tenant and application have dropdowns');

check(str_contains($mapping,'tenant_quickpick')&&str_contains($mapping,'domain-mapping-choice-list'),'publisher alternative rendered without requiring native dropdown');
check(str_contains($mapping,'eligible publisher account(s)')&&str_contains($mapping,'compatible application(s)'),'eligible option counts shown');
check(str_contains($mapping,'Why DCN / DCNEP is missing:')&&str_contains($mapping,'different product'),'DCN unavailable reason shown safely');
$requested=(new ReflectionMethod(\Digiti\Admin\DomainMapping::class,'requestedTenant'));
$amg='01a05e76-d968-7e72-a4ef-aec784cf0547';
$dcn='01a195d7-3a9a-761d-bde8-3965e712ccd1';
check($requested->invoke(null,['product_tenant_guid'=>$amg])===$amg,'normal dropdown value accepted');
check($requested->invoke(null,['product_tenant_guid'=>$amg,'tenant_quickpick'=>$dcn])===$dcn,'explicit DCN choice overrides stuck native dropdown');

check(str_contains($mapping,'WHERE pt.product_guid=? AND pt.status="ACTIVE"'),'tenant dropdown is product-scoped and active-only');
check(str_contains($mapping,'WHERE product_guid=? AND status="ACTIVE"'),'application dropdown is compatible and active-only');
check(str_contains($mapping,'Type the exact domain name')&&str_contains($mapping,'confirm_content'),'mapping update requires operator confirmation');
check(str_contains($mapping,'UPDATE domains SET organisation_guid=?,product_tenant_guid=?'),'mapping persists correct publisher organisation and tenant');
check(str_contains($mapping,'application_guid=?,is_primary=0,status="AWAITING_DNS"'),'mapping updates application and resets onboarding state');
check(str_contains($mapping,'domain.mapping.changed')&&str_contains($mapping,"RedisStore::del('domain:'.\$host)"),'mapping changes audited and Platform domain cache cleared');
$mutable=new ReflectionMethod(\Digiti\Admin\DomainMapping::class,'mutable');
$domainBase=['hostname'=>'epaper.dcnnews.co.in','base_url'=>'https://epaper.digiti.in','domain_type'=>'CUSTOMER_CUSTOM','status'=>'ROUTING_VALID'];
check($mutable->invoke(null,$domainBase),'customer domains can change publisher mapping');
check(!$mutable->invoke(null,array_merge($domainBase,['hostname'=>'epaper.digiti.in'])),'canonical application domain is protected');
check(!$mutable->invoke(null,array_merge($domainBase,['domain_type'=>'PLATFORM'])),'platform system domain is protected');
check(!$mutable->invoke(null,array_merge($domainBase,['status'=>'REMOVED'])),'removed domain is protected');

check(str_contains($home,'home-issue')&&str_contains($home,'organisation_name'),'homepage shows current tenant-mapping blockers');
check(str_contains($help,'Wrong newspaper appears')&&str_contains($help,'Product tenant'),'help explains tenant isolation');
check(str_contains($onboard,'$customCode!==')&&str_contains($panel,'Advanced options (optional)'),'publisher code generated with optional override');
check(str_contains($onboard,'This website shares the main ePaper newspaper.'),'domain tracker warns on shared tenant mapping');
check(str_contains($error,'$e instanceof \\InvalidArgumentException'),'user errors are separated from internal errors');
check(str_contains($css,'.platform-mobile-menu')&&str_contains($css,'@media(max-width:600px)'),'responsive workspace');
echo "Operator UI smoke checks completed.\n";
