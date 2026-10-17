<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$ok=function(bool$c,string$m)use(&$fails){echo($c?'PASS':'FAIL')." — {$m}\n";if(!$c)$fails[]=$m;};$read=fn(string$f)=>(string)@file_get_contents($root.'/'.$f);
$app=$read('app/App.php');$crud=$read('public_html/assets/crud.js');$css=$read('public_html/assets/admin-beta.css');$ready=$read('app/Services/ProductionReadiness.php');
foreach(['Backup & restore rehearsal','Publishing workflow','Reader acceptance','Encrypted offline acceptance','Public newsstand & archive','Publisher console UX','SEO & discovery','Recovery & observability'] as$label)$ok(str_contains($app,$label),'manual acceptance item: '.$label);
$ok(str_contains($app,'stored only in this browser'),'manual checklist is not presented as authoritative server state');
$ok(str_contains($app,'Automated checks are ready. Complete every manual item')&&str_contains($app,'Resolve all automated ATTENTION items'),'stable promotion wording distinguishes automated/manual gates');
$ok(str_contains($crud,"const remaining=[...pop.children].filter(el=>!el.classList.contains('record-form'))"),'overflow action preservation uses DOM structure, not collapsed visibility');
$ok(str_contains($crud,"form.setAttribute('aria-busy','true')")&&str_contains($crud,"setAttribute('aria-disabled','true')"),'forms expose busy/disabled state');
$ok(str_contains($css,'prefers-reduced-motion:reduce'),'reduced-motion support retained');
$ok(str_contains($css,'.admin-shell :focus-visible'),'visible keyboard focus retained');
$ok(str_contains($ready,"BUILD-MANIFEST-'.\$version.'.md"),'release integrity probe tracks current build dynamically');
if($fails){fwrite(STDERR,"FAIL\n - ".implode("\n - ",$fails)."\n");exit(1);}echo"\nPASS final acceptance static guard\n";
