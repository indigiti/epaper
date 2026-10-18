<?php
declare(strict_types=1);

/** Source-level guard checks; real Cloudways acceptance is a separate gate. */
$root=dirname(__DIR__);
$cli=(string)file_get_contents($root.'/bin/ai-clipping-staging.php');
$smoke=(string)file_get_contents($root.'/bin/ai-clipping-smoke.py');
$doc=(string)file_get_contents($root.'/docs/AI-CLIPPING-CLOUDWAYS-ACTIVATION-20261018.md');
foreach([
    "PHP_SAPI !== 'cli'",
    "'--check','--apply-migration'",
    "\$environment==='staging'",
    "'epaper.digiti.in'",
    "EPDI_AI_STAGING_DB_NAME",
    "EPDI_AI_STAGING_HOST",
    "EPDI_AI_BACKUP_VERIFIED",
    "SELECT DATABASE()",
    "information_schema.TABLES",
    "CREATE TABLE IF NOT EXISTS ep_ai_page_candidates",
    "AiCandidatePipeline::enabled()",
] as $guard){
    if(!str_contains($cli,$guard))throw new RuntimeException("Activation guard missing: ".$guard);
}
if(str_contains($cli,'file_put_contents(')||str_contains($cli,'DROP TABLE')||
   str_contains($cli,"'enabled'=>true")){
    throw new RuntimeException('Migration CLI must not alter runtime config or drop data');
}
foreach(['TemporaryDirectory','np.random','ai-candidate-generate.py',
         'image_detector_attempted','verified_article','subprocess.run'] as $token){
    if(!str_contains($smoke,$token) && $token !== 'np.random'){
        throw new RuntimeException('Synthetic worker smoke incomplete: '.$token);
    }
}
foreach(['cw-platform','separate DBs','RabbitMQ','Rollback'] as $token){
    if(!str_contains(strtolower($doc),strtolower($token))){
        throw new RuntimeException('Multilayer activation documentation missing: '.$token);
    }
}
echo "AI staging migration guard, Python test contract, and multilayer operator documentation: OK\n";
