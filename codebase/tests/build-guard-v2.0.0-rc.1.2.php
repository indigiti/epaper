<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$pass=function(bool$ok,string$name)use(&$fails){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fails[]=$name;};$read=fn(string$f):string=>is_file($root.'/'.$f)?(string)file_get_contents($root.'/'.$f):'';
$version=trim($read('VERSION'));$app=$read('app/App.php');$cache=$read('app/Services/PublicReadCache.php');$tenant=$read('app/Services/TenantData.php');$css=$read('public_html/assets/app.css');$sw=$read('public_html/service-worker.js');
$pass($version==='2.0.0-rc.1.2','VERSION locked to v2.0.0-rc.1.2');
foreach(['newsstand-hero','edition-rail','publication-rail','archive-cta'] as$c)$pass(str_contains($app,$c),'homepage public component '.$c);
foreach(['archive-date-panel','archive-date-rail','archive-quick-row','archive-toolbar','archive-grid'] as$c)$pass(str_contains($app,$c),'archive public component '.$c);
$pass(str_contains($cache,'function archiveCalendar(')&&str_contains($cache,'GROUP BY edition_date'),'cached archive calendar read model packaged');
$pass(str_contains($app,"'thumb'=>['thumb']"),'listing THUMB mode is strict');
$pass(str_contains($app,"'preview'=>['preview','thumb']"),'hero PREVIEW can fall back only to THUMB');
$pass(!str_contains($app,"['thumb','preview','highres']"),'listing covers never escalate THUMB to PREVIEW/HIGHRES');
$pass(str_contains($tenant,'ta.width thumb_width')&&str_contains($tenant,'pa.width preview_width'),'cover dimensions exposed for stable layout');
$pass(str_contains($app,'loading="lazy" decoding="async"'),'listing covers use lazy decode');
$pass(str_contains($app,'fetchpriority="high"'),'hero cover prioritized');
foreach(['.newsstand-hero{','.edition-rail{','.archive-date-rail{','.archive-grid{','@media(max-width:700px)','prefers-reduced-motion'] as$marker)$pass(str_contains($css,$marker),'responsive public CSS '.$marker);
$pass(str_contains($sw,"const VERSION='epdi-v2.0.0-rc.1.2'"),'service worker aligned to rc.1.2');
$pass(!is_file($root.'/database/upgrade-v2.0.0-rc.1.2.sql'),'rc.1.2 introduces no database migration');
foreach(['/archive','/edition/','/epaper/','/search'] as$route)$pass(str_contains($app,$route),'public route family retained '.$route);
if($fails){fwrite(STDERR,"\nRC.1.2 BUILD GUARD FAILED: ".implode(', ',$fails)."\n");exit(1);}echo"\nALL EP-DI v2.0.0-rc.1.2 BUILD GUARDS PASSED\n";
