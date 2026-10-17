<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$repo=dirname($root);
$checks=[
    'new repo landing page'=>is_file($repo.'/README.md'),
    'documentation index'=>is_file($root.'/docs/README.md'),
    'module map'=>is_file($root.'/docs/architecture/MODULE-MAP.md'),
    'developer guide'=>is_file($root.'/docs/development/README.md'),
    'operations index'=>is_file($root.'/docs/operations/README.md'),
    'historical integrity policy'=>is_file($root.'/docs/releases/README.md'),
    'document root preserved'=>is_file($root.'/public_html/index.php'),
    'autoload preserved'=>is_file($root.'/app/bootstrap.php'),
    'worker entry point preserved'=>is_file($root.'/bin/rabbit-worker.php'),
    'current release version preserved'=>is_file($root.'/VERSION'),
    'root GitHub CI preserved'=>is_file($repo.'/.github/workflows/epaper-ci.yml'),
    'historical SHA256SUMS preserved'=>is_file($root.'/SHA256SUMS'),
    'current build manifest pinned'=>is_file($root.'/BUILD-MANIFEST-2.0.2.1.md'),
    'current release check pinned'=>is_file($root.'/RELEASE-CHECK-2.0.2.1.txt'),
    'current update path pinned'=>is_file($root.'/UPDATE-2.0.2.1.md'),
];
$moved=[
 'APP-KEY-ROTATION-INVENTORY.md'=>'security',
 'SECURITY-CUTOVER-ATTESTATION.md'=>'security',
 'SECURITY-CUTOVER-RUNBOOK.md'=>'security',
 'SECURITY-STABILIZATION-2026-10-17.md'=>'security',
 'MULTILAYER-SECURITY-PREPOST-2026-10-17.md'=>'audits',
 'STAGING-ACCEPTANCE-HARNESS-2026-10-17.md'=>'testing'
];
foreach($moved as$name=>$section){
    $checks["active document $name relocated"]=is_file($root.'/docs/'.$section.'/'.$name)&&!is_file($root.'/'.$name);
}
$sum=(string)file_get_contents($root.'/SHA256SUMS');
$checks['legacy checksums still use pinned build-manifest path']=str_contains($sum,'  BUILD-MANIFEST-2.0.2.1.md');
$checks['legacy checksums still use pinned release-check path']=str_contains($sum,'  RELEASE-CHECK-2.0.2.1.txt');
$checks['legacy checksum includes current update path']=str_contains($sum,'  UPDATE-2.0.2.1.md');
$index=(string)file_get_contents($root.'/docs/README.md');
foreach(['security','audits','testing','architecture','development','operations','releases'] as$folder){
    $checks["documentation category $folder"]=is_dir($root.'/docs/'.$folder)&&str_contains($index,$folder.'/');
}
$failed=array_keys(array_filter($checks,static fn(bool $ok)=>!$ok));
if($failed){foreach($failed as$name)fwrite(STDERR,"FAIL: $name\n");exit(1);}
echo 'PASS repository layout and historical release entry-point preservation ('.count($checks)." checks)\n";
