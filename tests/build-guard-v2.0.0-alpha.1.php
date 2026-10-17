<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$fails=[];
$pass=function(bool $ok,string $name)use(&$fails): void {
    echo ($ok?'PASS':'FAIL')." — {$name}\n";
    if(!$ok)$fails[]=$name;
};
$read=fn(string $file): string => is_file($root.'/'.$file)?(string)file_get_contents($root.'/'.$file):'';

$version=trim($read('VERSION'));
$build=$read('app/Core/Build.php');
$redis=$read('app/Core/RedisStore.php');
$tenant=$read('app/Services/TenantData.php');
$app=$read('app/App.php');
$view=$read('app/Core/View.php');
$worker=$read('bin/rabbit-worker.php');
$outbox=$read('bin/outbox-publisher.php');
$appJs=$read('public_html/assets/app.js');
$reader=$read('public_html/assets/reader.js');
$sw=$read('public_html/service-worker.js');
$webManifest=$read('public_html/manifest.webmanifest');
$schema=$read('database/schema.sql');
$supervisor=$read('SUPERVISOR.conf.example');
$media=$read('app/Services/Media.php');

$pass($version==='2.0.0-alpha.1','VERSION locked to 2.0.0-alpha.1');
$pass(!is_file($root.'/config/config.php')&&!is_file($root.'/config/runtime.php'),'production config/secrets are not packaged');
$pass(str_contains($build,'final class Build')&&str_contains($build,'public static function asset')&&str_contains($build,"'/VERSION'"),'single build/version source exists');
$pass(str_contains($redis,'ttlWithJitter')&&str_contains($redis,"'hit_rate'")&&str_contains($redis,'public static function lock'),'Redis utility exposes jitter, telemetry and locks');

foreach([
    'scopedSettings','settings:v2:','editionsWithCovers','pagesWithAssets','pageWithAssets'
] as $needle)$pass(str_contains($tenant,$needle),'TenantData performance primitive '.$needle);
$pass(str_contains($schema,'ix_setting_scope (tenant_guid,scope_type,scope_guid,setting_key)'),'settings scope index included for new installs');
$pass(is_file($root.'/database/upgrade-v2.0.0-alpha.1.sql'),'existing-install settings index upgrade included');

$pass(str_contains($app,'TenantData::editionsWithCovers')&&str_contains($app,'TenantData::pagesWithAssets'),'hot paths use joined edition/page asset reads');
$pass(str_contains($app,"reader:state:v2:")&&str_contains($app,"reader:resume:v2:"),'Reader state/resume cache namespace upgraded');
$pass(!str_contains($app,'YEAR(e.edition_date)=?'),'Archive no longer wraps indexed edition date with YEAR()');
$pass(str_contains($app,'adminDashboardMetrics')&&str_contains($app,"dashboard:v2:"),'admin dashboard aggregate snapshot is Redis cached');
$pass(str_contains($app,'accountOverviewMetrics')&&str_contains($app,'thumb_asset_guid'),'Reader OS/Library avoids repeated count/thumbnail lookups');

$pass(str_contains($view,'Build::asset')&&str_contains($view,'Build::version'),'View assets and runtime use locked build version');
$pass(str_contains($view,'if($admin)$scripts')&&str_contains($view,"'/assets/crud.js'"),'admin CRUD JS is route-scoped');
$pass(str_contains($view,'structured_data')&&str_contains($view,'application/ld+json')&&str_contains($view,'$opts[\'lang\']'),'SSR supports semantic JSON-LD and language metadata');

$pass(str_contains($worker,'worker:heartbeat:v2:')&&str_contains($worker,'$lastDbTouch')&&str_contains($worker,'$maxJobs'),'Rabbit worker has Redis heartbeat, DB throttle and recycle limit');
$pass(str_contains($outbox,'worker:heartbeat:v2:outbox')&&str_contains($outbox,'$lastDbTouch>=15'),'outbox heartbeat favors Redis and throttles MariaDB writes');
$pass(str_contains($supervisor,'--max-jobs=250')&&str_contains($supervisor,'startretries=5'),'Supervisor example includes recycling and startup guardrails');
$pass(str_contains($media,'token_bucket_seconds')&&str_contains($media,'X-Accel-Redirect'),'media URLs are bucket-stable and Nginx offload is available when configured');

$pass(str_contains($appJs,"service-worker.js?v=")&&str_contains($appJs,"delay=render(data)>0?2000:10000")&&!str_contains($appJs,'timer=setInterval(poll,2000)'),'client polling is cache-busted and adaptive');
$pass(str_contains($appJs,'pointerenter')&&str_contains($appJs,'paintActive')&&!str_contains($appJs,"mousemove',()=>{active=i;render()"),'command palette avoids full rerender on pointer movement');
$pass(str_contains($reader,'window.EpaperReaderPrefetch')&&str_contains($reader,'needed=new Set()')&&str_contains($reader,'for(let y=y0;y<=y1;y++)'),'Reader reuses prefetch and virtualizes Deep Zoom tiles');
$pass(!str_contains($reader,'for(let y=0;y<level.rows;y++)for(let x=0;x<level.cols;x++)'),'Reader no longer mounts the entire highest-resolution tile grid');

preg_match('/const SHELL=\[(.*?)\];/s',$sw,$shellMatch);
$shell=$shellMatch[1]??'';
$pass(str_contains($sw,"const VERSION='epdi-v2.0.0-alpha.1'")&&str_contains($sw,'PAGE_CACHE'),'service worker is aligned with v2 build and navigation cache');
$pass(str_contains($shell,"'/offline.html'")&&!str_contains($shell,'reader.js')&&!str_contains($shell,'crud.js'),'install-time PWA shell is lean and has an offline fallback');
$pass(str_contains($sw,'privatePath')&&str_contains($sw,"policy.includes('no-store')"),'service worker does not persist private/no-store HTML');
$pass(str_contains($webManifest,'"shortcuts"')&&str_contains($webManifest,'/account/offline'),'PWA manifest exposes reader shortcuts');
$pass(is_file($root.'/public_html/offline.html'),'offline fallback document exists');
$pass(str_contains($sw,'legacy-reader-media')&&str_contains($sw,'Encrypted Offline Vault'),'plaintext media cache is explicitly marked temporary pending encrypted vault');

if($fails){
    fwrite(STDERR,"\nBUILD GUARD FAILED: ".implode(', ',$fails)."\n");
    exit(1);
}
echo "\nALL EP-DI v2.0.0-alpha.1 BUILD GUARDS PASSED\n";
