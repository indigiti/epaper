<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$ok=function(bool$c,string$m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};
$app=(string)file_get_contents($root.'/app/App.php');$view=(string)file_get_contents($root.'/app/Core/View.php');$css=(string)file_get_contents($root.'/public_html/assets/admin-beta.css');$crud=(string)file_get_contents($root.'/public_html/assets/crud.js');
$archiveMarker=<<<'SRC'
action="/admin/editions/'.$guid.'/archive"
SRC;
$restoreMarker=<<<'SRC'
action="/admin/editions/'.$guid.'/restore"
SRC;
$ok(str_contains($app,$archiveMarker)&&str_contains($app,$restoreMarker),'archive/restore POST endpoints are still used by Editions actions');
$ok(str_contains($app,'data-confirm="Archive this edition? Publishing intent will be cleared; processing/audit history is retained."'),'archive confirmation retained');
$ok(str_contains($app,'data-open-editor-target="edit-')&&str_contains($app,'data-open-editor-target="reprocess-'),'existing drawer workflows retained');
$ok(str_contains($crud,"action.classList.add('console-icon-action'")&&str_contains($crud,"action.setAttribute('aria-label',label)"),'promoted CRUD icons retain accessible labels');
$ok(str_contains($crud,"if(t.startsWith('delete'))")&&str_contains($crud,"if(t.startsWith('restore'))")&&str_contains($crud,"if(t.startsWith('view'))"),'generic View/Delete/Restore action mappings packaged');
$ok(str_contains($view,'New edition')&&str_contains($view,'console-create'),'header keeps a single obvious create action');
$ok(str_contains($css,'--console-sidebar:232px')&&str_contains($css,'--console-r-lg:10px'),'compact shell and restrained-radius tokens packaged');
$ok(str_contains($css,'.admin-shell .panel .record-stack>.record-row')&&str_contains($css,'border-width:0 0 1px'),'card-within-card reduction applied to record stacks');
$ok(str_contains($css,'.status.retry_wait')&&str_contains($css,'.status.failed')&&str_contains($css,'.status.published'),'semantic status language packaged');
if($fails){fwrite(STDERR,"\nADMIN CONSOLE UX STATIC FAILED\n - ".implode("\n - ",$fails)."\n");exit(1);}echo"\nPASS admin console UX static guard\n";
