<?php
declare(strict_types=1);
/**
 * Upgrade Center regression contract.
 * Covers one-purpose repair, simplified admin presentation and no obsolete
 * installed.lock schema gating. Does not contact a live Cloudways database.
 */
$root=dirname(__DIR__);
$read=static function(string $path) use ($root):string{
    $file=$root.'/'.$path;
    if(!is_file($file))throw new RuntimeException('Missing '.$path);
    return (string)file_get_contents($file);
};
$app=$read('app/App.php');
$schema=$read('app/Services/SchemaDoctor.php');
$css=$read('public_html/assets/admin-typography.css');
$version=trim($read('VERSION'));
$migration=$read('database/upgrade-v2.0.2.2-clip-marking.sql');
if($version!=='2.0.2.2')
    throw new RuntimeException('Build version must match Clip Marking migration');
if(!str_contains($migration,'CREATE TABLE IF NOT EXISTS ep_clip_marks'))
    throw new RuntimeException('Clip Marking migration must be additive');
foreach([
    'public static function repairClipMarking',
    'upgrade-v2.0.2.2-clip-marking.sql',
    "if(!self::tableExists(self::CLIP_MARK_TABLE))",
    "'epdi:v2:schema-upgrade'",
    'RELEASE_LOCK(?)',
    "if(empty(\$before['base_ok']))",
    "self::report(true)",
] as $token){
    if(!str_contains($schema,$token))
        throw new RuntimeException('Targeted schema repair contract missing '.$token);
}
if(str_contains($schema,"self::check('runtime:version-lock'"))
    throw new RuntimeException('Legacy installed.lock must not block database readiness');
foreach([
    "SchemaDoctor::repairClipMarking(\$actor)",
    "Auth::requireInfrastructureAdmin()",
    "if((string)(\$_POST['confirm']??'')!=='1')",
    "in_array(\$target,['all','clip-marking'],true)",
    'name="repair_target" value="clip-marking"',
    'name="confirm" value="1" required',
    'Repair Clip Marking table',
    'OTHER DATABASE MIGRATIONS',
    'upgrade-passed-details',
    'passed checks — show details',
    'Re-run checks only',
    "Audit::log('system.schema.repair'",
] as $token){
    if(!str_contains($app,$token))
        throw new RuntimeException('Upgrade Center UI or protection missing '.$token);
}
foreach(['.upgrade-target-repair','.upgrade-passed-details','.upgrade-issue',
           '@media(max-width:680px)'] as $token){
    if(!str_contains($css,$token))
        throw new RuntimeException('Upgrade Center UI styling missing '.$token);
}
echo "Scoped Clip Marking repair, optional installer marker, and simplified Upgrade Center: OK\n";
