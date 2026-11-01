<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/Core/Config.php';
require_once $root.'/app/Core/View.php';
require_once $root.'/app/Admin/ActionUi.php';
require_once $root.'/app/Admin/Restore.php';
require_once $root.'/app/Admin/StagingReset.php';

function assertAction(bool $ok,string $message):void {
    if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}
use Digiti\Admin\ActionUi;
use Digiti\Admin\StagingReset;
use Digiti\Admin\Restore;

$icons=['view','edit','plus','save','pause','play','archive','restore','more','rotate','check','trash'];
foreach($icons as $name){
    $svg=ActionUi::icon($name);
    assertAction(str_contains($svg,'<svg')&&str_contains($svg,'aria-hidden="true"'),"accessible SVG icon: $name");
}
assertAction(str_contains(ActionUi::link('/admin/domains','View','view'),'aria-label="View"'),'navigation controls have accessible names');

$stagingAllowed=(new ReflectionMethod(StagingReset::class,'eligible'));
$base=['organisation_code'=>'TEST_DC_NEWS','hostname'=>'epaper.testdc.example',
       'domain_type'=>'CUSTOMER_CUSTOM','base_url'=>'https://epaper.digiti.in'];
assertAction($stagingAllowed->invoke(null,$base),'tagged test publisher domain may reset');
assertAction(!$stagingAllowed->invoke(null,array_merge($base,['organisation_code'=>'DCN'])),'real publisher cannot reset');
assertAction(!$stagingAllowed->invoke(null,array_merge($base,['hostname'=>'epaper.digiti.in'])),'canonical hostname cannot reset');
assertAction(!$stagingAllowed->invoke(null,array_merge($base,['domain_type'=>'PLATFORM'])),'system hostname cannot reset');
assertAction(!StagingReset::enabled(),'staging reset disabled without private explicit opt-in');

$action=file_get_contents($root.'/app/Admin/ActionUi.php');
$restore=file_get_contents($root.'/app/Admin/Restore.php');
$reset=file_get_contents($root.'/app/Admin/StagingReset.php');
$app=file_get_contents($root.'/app/App.php');
$crud=file_get_contents($root.'/app/Admin/Crud.php');
$view=file_get_contents($root.'/app/Core/View.php');
$css=file_get_contents($root.'/public_html/assets/platform-ui.css');

assertAction(str_contains($app,'Restore::run(')&&str_contains($app,'StagingReset::run('),'restore and staging-only routes wired');
assertAction(str_contains($crud,'return ActionUi::row('),'primary registries share one action component');
assertAction(str_contains($app,"rowActions('domain'"),'domains use shared actions too');
assertAction(str_contains($action,"if(\$status==='REMOVED')") && str_contains($action,"/restore"),'archived rows offer restore, not direct activate');
assertAction(str_contains($action,"if(\$status==='SUSPENDED')"),'suspended records offer activation');
assertAction(str_contains($action,"if(\$entity==='service-account')"),'service accounts have distinct token-revocation handling');
assertAction(str_contains($restore,'status="AWAITING_DNS"') && str_contains($restore,'status="REVOKED"'),'restored domains and tokens cannot autoactivate');
assertAction(str_contains($restore,'checkDependencies($type,$record)'), 'restoration validates parents');
assertAction(str_contains($restore,'registry.restore'), 'restoration writes auditable event');
assertAction(str_contains($crud,'ensureNotRemoved('), 'edit and status endpoints cannot revive archived records directly');
assertAction(str_contains($crud,'Canonical or system domains cannot be archived here.'),'system domain archive blocked');
assertAction(str_contains($app,'This hostname is archived.'),'duplicate hostname creation points operators to restore');
assertAction(str_contains($view,'StagingReset::enabled()'),'staging reset menu hidden by default');
assertAction(str_contains($reset,"Config::get('app.allow_staging_reset',false)===true"),'staging reset requires explicit private config switch');
assertAction(str_contains($reset,"['staging','testing','development']"),'reset requires non-production environment');
assertAction(str_contains($reset,'platform.digiti.in'),'reset blocks production hostname');
assertAction(str_contains($reset,'confirm_hostname')&&str_contains($reset,'acknowledge'),'reset requires typed hostname and checked acknowledgement');
assertAction(str_contains($reset,'domain.staging_reset')&&str_contains($reset,'RedisStore::del('),'reset audit and cache invalidation');
assertAction(str_contains($css,'.platform-action-bar')&&str_contains($css,'.stage-reset-row'),'responsive action/reset stylesheet');

echo "Registry actions and protected staging reset smoke checks complete.\n";
