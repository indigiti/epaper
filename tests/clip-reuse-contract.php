<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$load=static fn(string $file):string=>(string)file_get_contents($root.'/'.$file);
$service=$load('app/Services/ClipService.php');
$cache=$load('app/Services/ClipCropCache.php');
$mark=$load('app/Services/ManualClipMarking.php');
$reader=$load('public_html/assets/reader.js');
$overlay=$load('public_html/assets/reader-editorial-clips.js');
$app=$load('app/App.php');
$schema=$load('database/schema.sql');
$upgrade=$load('app/Services/SchemaDoctor.php');
$index=$load('database/upgrade-v2.0.2.3-clip-reuse.sql');
foreach([
 'class ClipCropCache','hash_file(',"flock(\$lock,LOCK_EX)",'random_bytes(8)',
 'rename($tmp,$cache)','link($cache,$destination)','copy($cache,$destination)',
 'self::RETAIN_SECONDS','function prune(string $tenant',
] as $token){
 if(!str_contains($cache,str_replace('\"','"',$token)))
  throw new RuntimeException('Missing private crop cache guarantee: '.$token);
}
foreach([
 'ClipCropCache::materialize(',
 'ManualClipMarking::validateClipSource(',
 'SchemaDoctor::clipReuseReady()',
 'mark_guid,clip_slug,title,visibility',
 'catch(\\Throwable $e)',
 "'crop_cache_hit'=>\$cache['hit']",
 "'expires_in_days'=>\$editorial?null:self::EXPIRY_DAYS",
 "if(!\$editorial)PlatformClient::instance()->usage",
] as $token)
 if(!str_contains($service,$token))
   throw new RuntimeException('Reader clip creation lost cache, quota, expiry or mark verification: '.$token);
foreach([
 'm.mark_guid,m.title,m.x,m.y,m.width,m.height',
 "['mark_guid'=>Uuid::fromBinary(",
 'function validateClipSource(',
 'm.status="ACTIVE" AND e.status="PUBLISHED"',
 "m.tenant_guid=? AND m.page_guid=? AND m.mark_guid=?",
 "Article marking coordinates have changed",
] as $token)
 if(!str_contains($mark,$token))
  throw new RuntimeException('Approved mark association lacks secure checks: '.$token);
if(!str_contains($overlay,'validGuid(c.mark_guid)')||
   !str_contains($reader,'pendingClipMarkGuid')||
   !str_contains($reader,'mark_guid:pendingClipMarkGuid')||
   !str_contains($reader,'mark_guid:marker.mark_guid')||
   !str_contains($app,"isset(\$d['mark_guid'])"))
 throw new RuntimeException('Marker ID is lost in Reader creation request');
if(!str_contains($schema,'mark_guid BINARY(16) NULL')||
   !str_contains($schema,'ix_clip_mark(tenant_guid,page_guid,mark_guid,created_at)')||
   !str_contains($index,'ALTER TABLE ep_clips ADD COLUMN mark_guid'))
 throw new RuntimeException('Clipping mark association migration missing');
foreach([
 'function clipReuseReady(): bool',
 'function repairClipReuse(',
 'self::repairClipReuse($pdo,$actions)',
 'clip-reuse:mark',
 'v2.0.2.3 clipping mark reference',
] as $token)
 if(!str_contains($upgrade,$token))
  throw new RuntimeException('Web-based additive migration readiness/repair missing: '.$token);
if(str_contains($cache,'window.location')||str_contains($cache,'public_html')||
   str_contains($cache,'user_guid')||str_contains($cache,'expires_at')||
   str_contains($cache,'visibility'))
 throw new RuntimeException('Shared crop cache cannot carry user-specific data');
echo "Clipping reuse: existing guest/subscriber security and marked-article linkage OK\n";
