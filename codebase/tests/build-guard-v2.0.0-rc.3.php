<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$pass=function(bool$ok,string$name)use(&$fails){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fails[]=$name;};$read=fn(string$f):string=>is_file($root.'/'.$f)?(string)file_get_contents($root.'/'.$f):'';
$version=trim($read('VERSION'));$app=$read('app/App.php');$ready=$read('app/Services/ProductionReadiness.php');$crud=$read('public_html/assets/crud.js');$adminJs=$read('public_html/assets/admin-beta.js');$css=$read('public_html/assets/admin-beta.css');$sw=$read('public_html/service-worker.js');
$pass($version==='2.0.0-rc.3','VERSION locked to v2.0.0-rc.3');
$pass(str_contains($app,'MANUAL ACCEPTANCE MATRIX')&&str_contains($app,'Stable promotion gate'),'Release Audit contains final manual acceptance matrix');
$pass(str_contains($app,"\$runtimeOk=(string)(\$readiness['status']??'ACTION_REQUIRED')==='READY'"),'Stable automated gate requires fully READY runtime');
$pass(str_contains($adminJs,'epdi.finalAcceptance.')&&str_contains($adminJs,'data-acceptance-print'),'browser-local acceptance convenience is packaged');
$pass(str_contains($crud,"form.setAttribute('aria-busy','true')")&&str_contains($crud,'submitter.disabled=true'),'double-submit/busy interaction hardening packaged');
$pass(!str_contains($crud,'getClientRects().length!==0'),'collapsed More-menu actions are not discarded by visibility measurement');
$pass(str_contains($css,'.final-acceptance')&&str_contains($css,'.acceptance-item')&&str_contains($css,'@media print'),'acceptance + print design components packaged');
$pass(str_contains($css,'.admin-shell small{font-size:11px}')&&str_contains($css,'.console-icon-action{min-width:40px!important;min-height:40px!important}'),'operational legibility/touch-target floor packaged');
$pass(str_contains($app,'Front page of ')&&substr_count($app,'thumb_width')>=3,'publication cover accessibility/dimension metadata retained');
$pass(str_contains($ready,"BUILD-MANIFEST-'.\$version.'.md"),'readiness validates current-version build manifest dynamically');
$pass(str_contains($sw,"const VERSION='epdi-v2.0.0-rc.3'"),'service worker aligned to rc.3');
$pass(!is_file($root.'/database/upgrade-v2.0.0-rc.3.sql'),'rc.3 introduces no database migration');
foreach(['/admin/editions','/admin/processing','/admin/system/health','/admin/system/release-audit','/archive'] as$route)$pass(str_contains($app,$route),'critical route retained '.$route);
if($fails){fwrite(STDERR,"\nRC.3 BUILD GUARD FAILED: ".implode(', ',$fails)."\n");exit(1);}echo"\nALL EP-DI v2.0.0-rc.3 BUILD GUARDS PASSED\n";
