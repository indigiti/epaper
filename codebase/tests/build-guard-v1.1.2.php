<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fails=[];$pass=function(bool $ok,string $name)use(&$fails){echo($ok?'PASS':'FAIL')." — {$name}\n";if(!$ok)$fails[]=$name;};
$pass(trim((string)file_get_contents($root.'/VERSION'))==='1.1.2','VERSION 1.1.2');
$pass(!is_file($root.'/config/config.php'),'no production config/secrets packaged');
foreach(['ROUTES','CAPABILITIES','SETTINGS-MANIFEST','ANALYTICS-EVENTS','TENANT-DATA-MANIFEST','READER-BEHAVIORS','PROCESSING-STAGES','CITY-EPAPER-COMPATIBILITY'] as$n)$pass(is_file($root.'/release/'.$n.'-1.1.2.json'),'release manifest '.$n);
$routes=json_decode((string)file_get_contents($root.'/release/ROUTES-1.1.2.json'),true);$routeText=json_encode($routes,JSON_UNESCAPED_SLASHES);
foreach(['/media/{token}','/edition/{slug}/page/{page}','/api/v1/pages/{page_guid}/tiles','/api/v1/pages/{page_guid}/tiles/{level}/{x}/{y}.webp','/api/v1/clips','/account/saved-articles','/account/offline','/admin/processing/{edition_guid}','/admin/settings/reader','/admin/system/release-audit','/admin/system/infrastructure'] as$r)$pass(str_contains($routeText,$r),'protected route '.$r);
$cap=json_decode((string)file_get_contents($root.'/release/CAPABILITIES-1.1.2.json'),true)['capabilities']??[];
foreach(['saas.tenant_isolation','public.continue_reading','public.archive_filters','reader.fit_page','reader.fit_width','reader.cursor_centered_zoom','reader.pinch','reader.seamless_navigation','reader.clip','reader.smart_article','reader.resume','account.saved_articles','account.offline','publisher.settings_grouped','processing.rabbitmq','processing.supervisor_workers','processing.transactional_outbox','processing.ocr_language_profile','operations.release_guard','design.command_palette'] as$c)$pass(isset($cap[$c]),'capability '.$c);
$reader=(string)file_get_contents($root.'/public_html/assets/reader.js');foreach(['fitPageScale','fitWidthScale','reader.scrollZoomEnabled','data-clip-actions','history.pushState','popstate','prefetchAdjacent','pointerdown','pinching','reader_clip_started','reader_clip_saved','article-at','/api/v1/offline','epaper.progress.'] as$x)$pass(str_contains($reader,$x),'reader behavior '.$x);
$pass(str_contains($reader,"const defaultFit=(root.dataset.defaultFit==='width'?'width':'page')"),'reader honors scoped default fit');
$pass(str_contains($reader,"root.dataset.scrollZoomDefault==='on'"),'reader honors scoped scroll-zoom default');
$pass(!str_contains($reader,'min:0.5'),'reader has no legacy absolute 0.5 mobile zoom floor');
$app=(string)file_get_contents($root.'/app/App.php');foreach(['/admin/settings/(appearance|brand|seo|reader|processing|clipping)','adminProcessingDetail','adminReleaseAudit','publicClipImage','Continue reading'] as$x)$pass(str_contains($app,$x),'application parity guard '.$x);
$settings=(string)file_get_contents($root.'/app/Services/SettingsCatalog.php');foreach(['Publisher Pro','Executive Slate','Midnight Desk','Editorial Luxe','Metro Pulse','Heritage Press','Apple Editorial','Split Editorial','Gradient Focus','Minimal Access'] as$t)$pass(str_contains($settings,$t),'theme catalog '.$t);
$pipeline=(string)file_get_contents($root.'/app/Services/ProcessingPipeline.php');foreach(['EDITION_RENDER','PAGE_ASSETS','PAGE_OCR','EDITION_FINALIZE','mar+eng','hin+eng'] as$x)$pass(str_contains($pipeline,$x),'processing parity '.$x);
$fallback=(string)file_get_contents($root.'/app/Services/Processing.php');$pass(str_contains($pipeline,"'HIGHRES', 'image/webp'")||str_contains($pipeline,"'HIGHRES','image/webp'"),'Rabbit pipeline HIGHRES is WebP');$pass(str_contains($fallback,"'HIGHRES', 'image/webp'")||str_contains($fallback,"'HIGHRES','image/webp'"),'database fallback HIGHRES is WebP');$pass(!str_contains($pipeline,"'HIGHRES', 'image/jpeg'")&&!str_contains($fallback,"'HIGHRES', 'image/jpeg'"),'no reader HIGHRES JPEG registration');$pass(str_contains($pipeline,'source_render_path'),'private renderer source separated from reader HIGHRES');
$rabbit=(string)file_get_contents($root.'/app/Services/RabbitMq.php');$outbox=(string)file_get_contents($root.'/app/Services/OutboxPublisher.php');$worker=(string)file_get_contents($root.'/bin/rabbit-worker.php');foreach(['epaper.render','epaper.assets','epaper.ocr','epaper.finalize','epaper.dead'] as$q)$pass(str_contains($rabbit,$q),'RabbitMQ queue '.$q);$pass(str_contains($outbox,'FOR UPDATE SKIP LOCKED'),'transactional outbox claim');$pass(str_contains($worker,'JOB_LOCK_BUSY_REQUEUED')||str_contains($worker,'requeue'),'worker lock-busy is not silently ACKed');
$catalog=require_once $root.'/app/Services/AnalyticsCatalog.php';$events=json_decode((string)file_get_contents($root.'/release/ANALYTICS-EVENTS-1.1.2.json'),true)['events']??[];$pass($events===\EpaperSaas\Services\AnalyticsCatalog::EVENTS,'analytics manifest equals backend registry');preg_match_all("/track\\(\\s*['\"]([^'\"]+)['\"]/",$reader,$m);foreach(array_unique($m[1]) as$e)$pass(in_array($e,$events,true),'frontend event registered '.$e);
$sw=(string)file_get_contents($root.'/public_html/service-worker.js');$pass(str_contains($sw,"epaper-saas-v1.1.2")&&substr_count($sw,'v=1.1.2')>=4,'service worker and assets aligned');
$schema=(string)file_get_contents($root.'/database/schema.sql');preg_match_all('/CREATE TABLE IF NOT EXISTS\s+([a-z0-9_]+)/i',$schema,$m);$pass(count($m[1])>=28,'SaaS schema retained');$pass(count($m[1])===count(array_unique($m[1])),'no duplicate tables');
$compat=json_decode((string)file_get_contents($root.'/release/CITY-EPAPER-COMPATIBILITY-1.1.2.json'),true);$pass(($compat['baseline']??'')==='City ePaper v2.2.9.1','City compatibility baseline locked');
// Full baseline deletion guards, not just protected route samples.
$baseRoutes=json_decode((string)file_get_contents($root.'/release/ROUTES-BASELINE.json'),true)['routes']??[];$newRoutes=$routes['routes']??[];foreach($baseRoutes as$group=>$items){$have=$newRoutes[$group]??[];foreach($items as$item)$pass(in_array($item,$have,true),'baseline route retained '.$item);}
$baseCaps=json_decode((string)file_get_contents($root.'/release/CAPABILITIES-BASELINE.json'),true)['capabilities']??[];foreach(array_keys($baseCaps) as$key)$pass(array_key_exists($key,$cap),'baseline capability retained '.$key);
$baseSettings=json_decode((string)file_get_contents($root.'/release/SETTINGS-MANIFEST-1.0.1.2.json'),true)['settings']??[];$newSettings=json_decode((string)file_get_contents($root.'/release/SETTINGS-MANIFEST-1.1.2.json'),true)['settings']??[];foreach($baseSettings as$key)$pass(in_array($key,$newSettings,true),'baseline setting retained '.$key);
// Management/settings wiring guards: settings must affect runtime, not only appear in the dashboard.
$runtimeText='';foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app',FilesystemIterator::SKIP_DOTS)) as$f){if($f->isFile()&&$f->getExtension()==='php'&&$f->getFilename()!=='SettingsCatalog.php')$runtimeText.=file_get_contents($f->getPathname())."
";}$runtimeText.=file_get_contents($root.'/public_html/assets/reader.js');
require_once $root.'/app/Services/SettingsCatalog.php';foreach(\EpaperSaas\Services\SettingsCatalog::keys() as$key)$pass(str_contains($runtimeText,$key),'setting wired '.$key);
foreach(['data-clipping-enabled','data-smart-article-enabled','data-prefetch-enabled','data-offline-enabled','CLIPPING_DISABLED','OFFLINE_DISABLED'] as$x)$pass(str_contains($app,$x),'reader/settings enforcement '.$x);
$clip=(string)file_get_contents($root.'/app/Services/ClipService.php');foreach(['clipping.watermark','clipping.expiry_days','expires_at'] as$x)$pass(str_contains($clip,$x),'clipping managed setting '.$x);
$pass(str_contains($fallback,'function ocrLanguagesForPage'),'database fallback OCR language profile');
foreach(['processing.pdf_dpi','processing.preview_width','processing.thumbnail_width','processing.tile_size','processing.auto_language'] as$x)$pass(str_contains($pipeline,$x)&&str_contains($fallback,$x),'processing setting applied '.$x);
// Catch accidental undefined $this->method() calls in major runtime classes.
foreach(['app/App.php','app/Services/Processing.php','app/Services/ProcessingPipeline.php'] as$file){$txt=(string)file_get_contents($root.'/'.$file);preg_match_all('/(?:public|private|protected)\s+(?:static\s+)?function\s+([A-Za-z_]\w*)\s*\(/',$txt,$dm);preg_match_all('/\$this->([A-Za-z_]\w*)\s*\(/',$txt,$cm);$missing=array_values(array_diff(array_unique($cm[1]),array_unique($dm[1])));$pass(!$missing,'method-call integrity '.$file);}
$view=(string)file_get_contents($root.'/app/Core/View.php');$appCss=(string)file_get_contents($root.'/public_html/assets/app.css');$appJs=(string)file_get_contents($root.'/public_html/assets/app.js');
foreach(['app-workspace','app-sidebar','sidebar-context','role-pill','accountNav'] as$x){$ok=str_contains($view,$x)||str_contains($appCss,$x);$pass($ok,'workspace UX '.$x);}$pass(isset($cap['publisher.settings_consolidated_navigation']),'workspace UX settings consolidated navigation');
foreach(['library-media-card','notification-feed','reading-progress','clip-gallery-card','Continue reading'] as$x)$pass(str_contains($app,$x)||str_contains($appCss,$x),'account visual UX '.$x);
$pass(str_contains($reader,"4600"),'reader idle threshold extended');
$pass(str_contains((string)file_get_contents($root.'/public_html/assets/reader.css'),'.reader-shell.reading-active .reader-header{opacity:1!important}'),'reader header persistent during idle');
$pass(str_contains((string)file_get_contents($root.'/public_html/assets/reader.css'),'.reader-shell.reading-active .reader-pager{opacity:1!important}'),'reader pager persistent during idle');
$pass(str_contains($appJs,"role-aware publisher command palette")&&str_contains($appJs,".app-sidebar .app-nav-link"),'command palette follows visible role-aware nav');
// v1.1.2 admin appearance + luxury operations guards.
$pass(str_contains($view,'data-theme-toggle')&&str_contains($view,'adminColorMode'),'admin per-user theme control rendered');
$pass(str_contains($app,"/admin/preferences/theme")&&str_contains($app,'adminThemePreference'),'admin theme preference endpoint wired');
$pass(str_contains($app,'preferences_json')&&str_contains($app,'admin_theme_mode'),'admin theme persisted without schema migration');
$pass(str_contains($appJs,"/admin/preferences/theme")&&str_contains($appJs,'Appearance change could not be saved'),'theme toggle optimistic save + rollback');
$pass(str_contains($appCss,'.health-score-ring')&&str_contains($appCss,'.lux-health-card')&&str_contains($appCss,'.detail-metric-grid'),'luxury system health UI');
$pass(str_contains($view,'ops-hud')&&str_contains((string)file_get_contents($root.'/app/Core/Store.php'),'query_ms'),'admin DB performance HUD instrumentation');
$pass(str_contains((string)file_get_contents($root.'/app/Core/RedisStore.php'),'hit_rate'),'admin cache hit-rate instrumentation');
$pass(in_array('POST /admin/preferences/theme',$routes['routes']['domain_admin']??[],true),'theme preference route manifest');
$pass(isset($cap['design.admin_user_light_dark_mode']),'theme preference capability');
$pass(isset($cap['publisher.health_luxury_dashboard']),'luxury health capability');

if($fails){fwrite(STDERR,"\nBUILD GUARD FAILED: ".implode(', ',$fails)."\n");exit(1);}echo "\nALL v1.1.2 BUILD GUARDS PASSED\n";
