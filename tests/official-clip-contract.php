<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$read=static fn(string $p):string=>(string)file_get_contents($root.'/'.$p);
$official=$read('app/Services/OfficialClipService.php');
$service=$read('app/Services/ClipService.php');
$marks=$read('app/Services/ManualClipMarking.php');
$controller=$read('app/Controllers/AdminManualClippingController.php');
$view=$read('app/Views/ManualClippingView.php');
$reader=$read('public_html/assets/reader-editorial-clips.js');
$app=$read('app/App.php');
$schema=$read('database/schema.sql');
$migration=$read('database/upgrade-v2.0.2.4-official-clips.sql');
$doctor=$read('app/Services/SchemaDoctor.php');

foreach([
 'private const MANAGERS','OWNER','ADMIN','PUBLISHER_ADMIN',
 'Auth::currentMembership()',
 "WHERE m.tenant_guid=? AND m.page_guid=? AND m.mark_guid=?",
 'm.status="ACTIVE" AND e.status="PUBLISHED"',
 'c.visibility="OFFICIAL"',
 'c.expires_at IS NULL',
 'm.updated_at=o.mark_updated_at',
 'p.version=o.page_version',
 'a.asset_guid=o.asset_guid',
 '(CAST(a.checksum_sha256 AS BINARY) <=> CAST(o.asset_checksum AS BINARY))',
 'o.tenant_guid=? AND o.mark_guid=? AND o.page_guid=? AND o.clip_guid=?',
 'GET_LOCK(?,10)','RELEASE_LOCK(?)',
 'status="REVOKED"',
 'ClipService::createOfficial(',
 'ClipService::prepareFormat(',
 'clip.official_approved','clip.official_withdrawn',
] as $token){
 if(!str_contains($official,$token))
   throw new RuntimeException('Publisher approval/withdrawal or revocation protection missing: '.$token);
}
if(str_contains($official,'visibility="PRIVATE"') ||
   str_contains($official,'UPDATE ep_clips SET visibility') ||
   str_contains($official,'DELETE FROM ep_clips'))
 throw new RuntimeException('Publisher approval may never promote/delete existing reader clips');
foreach([
 "if(\$official){",
 "'OFFICIAL'",
 "'PUBLISHER_OFFICIAL'",
 'return OfficialClipService::mayView($clip)',
 "return in_array(\$role,['OWNER','ADMIN','PUBLISHER_ADMIN'],true)",
 "public static function createOfficial(",
 "'expires_in_days'=>\$editorial?null:self::EXPIRY_DAYS",
 "if(!\$editorial)PlatformClient::instance()->usage",
] as $token){
 if(!str_contains($service,$token))
   throw new RuntimeException('Clipping privacy, quota or manager-only official type regressed: '.$token);
}
foreach([
 'OfficialClipService::urlsForPage($pageGuid)',
 "'official_url'=>\$official[\$markGuid]??null",
 "['official_url']=\$official[\$r['mark_guid']]??null",
 'm.status="ACTIVE"',
] as $token)if(!str_contains($marks,$token))
 throw new RuntimeException('Official link not properly scoped to active reader marks: '.$token);
foreach([
 'publish-official|withdraw-official',
 'OfficialClipService::approve',
 'OfficialClipService::withdraw',
 'self::limitActions()',
 'AdminManualClippingController',
] as $token)
 if(!str_contains($controller,$token))
  throw new RuntimeException('Admin approval/withdrawal POST workflow missing: '.$token);
foreach([
 'manual-official-actions','Open publisher-approved clipping',
 'publish-official','withdraw-official',
 'Publisher administrators can approve',
 'name="_csrf"','data-confirm=',
] as $token)
 if(!str_contains($view,$token))
  throw new RuntimeException('Accessible approval UI or CSRF confirmation missing: '.$token);
foreach([
 'official_url', 'window.location.assign(official)',
 "button.dataset.officialClip='1'",
 'window.EpdiReader?.createFromEditorialMarker?.(c,guid,pageNumber',
] as $token)
 if(!str_contains($reader,$token))
  throw new RuntimeException('Reader direct canonical link or fallback clipping missing: '.$token);
if(!str_contains($app,"['PRIVATE','OFFICIAL']") ||
   !str_contains($app,'private, no-store'))
 throw new RuntimeException('Revocable official clip images must bypass public caching');
if(!str_contains($doctor,'function officialReady(): bool')||
 !str_contains($doctor,"upgrade-v2.0.2.4-official-clips.sql")||
 substr_count($doctor,'self::repairOfficialClips($pdo,$actions)')!==2 ||
 !str_contains($doctor,'migration:official-clips:table'))
 throw new RuntimeException('Safe idempotent web upgrade / readiness missing');
foreach([
 'CREATE TABLE IF NOT EXISTS ep_official_clips',
 'PRIMARY KEY(tenant_guid,mark_guid)',
 'KEY ix_official_page(tenant_guid,page_guid,status)',
 'KEY ix_official_clip(tenant_guid,clip_guid,status)',
 'mark_updated_at DATETIME(6)',
 'approved_by_user_guid BINARY(16)',
] as $token){
 if(!str_contains($schema,$token)||!str_contains($migration,$token))
  throw new RuntimeException('Official link migration incomplete: '.$token);
}
echo "Publisher-approved clipping: tenant isolation, withdrawal, Reader fallback and web schema repair OK\n";
