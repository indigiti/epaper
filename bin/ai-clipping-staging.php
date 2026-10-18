<?php
declare(strict_types=1);

/**
 * Cloudways ePaper AI staging preflight + opt-in, isolated candidate migration.
 * CLI only. Never enables AI, runs an OCR job or modifies an existing table.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require dirname(__DIR__) . '/app/bootstrap.php';

use EpaperSaas\Core\{Config,Database};
use EpaperSaas\Services\AiCandidatePipeline;

$mode=$argv[1]??'--check';
if(!in_array($mode,['--check','--apply-migration'],true)||count($argv)>2){
    fwrite(STDERR,"Usage: php bin/ai-clipping-staging.php [--check|--apply-migration]\n");
    exit(2);
}

$root=dirname(__DIR__);
$environment=strtolower(trim((string)Config::get('app.environment','production')));
$url=(string)Config::get('app.url','');
$host=strtolower((string)(parse_url($url,PHP_URL_HOST)?:''));
$dbName=(string)Config::get('db.name','');
$staging=$environment==='staging'&&$host!==''&&!in_array($host,
    ['epaper.digiti.in','platform.digiti.in','localhost','127.0.0.1'],true);
$schema='UNAVAILABLE';
$dbActual=null;
try{
    $db=Database::pdo();
    $dbActual=(string)$db->query('SELECT DATABASE()')->fetchColumn();
    $p=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $p->execute(['ep_ai_page_candidates']);
    $schema=(int)$p->fetchColumn()>0?'PRESENT':'MISSING';
}catch(\Throwable){
    // Never echo SQL error messages, DSNs, usernames or passwords.
}

$binary=(string)Config::get('ai_clipping.python_binary','python3');
$absolute=str_starts_with($binary,'/');
$python=$absolute&&is_file($binary)&&is_executable($binary)?'BINARY_PRESENT':'UNVERIFIED';
$code=is_file($root.'/bin/ai-candidate-generate.py')&&
    is_file($root.'/ai_processing/hocr.py')&&
    is_file($root.'/ai_processing/segmentation.py');
$plan=[
    'application'=>'EPAPER',
    'environment'=>$environment,
    'staging_guard'=>$staging?'PASS':'BLOCKED',
    'ai_enabled'=>AiCandidatePipeline::enabled(),
    'candidate_schema'=>$schema,
    'database_selected'=>$dbActual!==null&&hash_equals($dbName,$dbActual),
    'python_binary'=>$python,
    'source_modules'=>$code?'PRESENT':'MISSING',
    'activation_note'=>'Check Python/OpenCV using the configured interpreter: <python> bin/ai-clipping-smoke.py; verify RabbitMQ OCR separately.',
];

if($mode==='--check'){
    echo json_encode($plan,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    exit(0);
}

// DDL has an implicit commit. Require staging identity, independently verified
// backup and explicit database+hostname matching before touching any schema.
$confirmHost=(string)(getenv('EPDI_AI_STAGING_HOST')?:'');
$confirmDb=(string)(getenv('EPDI_AI_STAGING_DB_NAME')?:'');
$backup=(string)(getenv('EPDI_AI_BACKUP_VERIFIED')?:'');
if(!$staging||$schema==='UNAVAILABLE'||!$code||
   $dbName===''||$dbActual===null||!hash_equals($dbName,$dbActual)||
   !hash_equals($host,strtolower($confirmHost))||
   !hash_equals($dbName,$confirmDb)||$backup!=='yes'){
    fwrite(STDERR,"BLOCKED: isolated staging environment, matching DB/host and verified backup are required. No schema changes made.\n");
    exit(3);
}
if($schema==='PRESENT'){
    echo "Candidate table already exists; no changes made.\n";
    exit(0);
}
$path=$root.'/database/upgrade-ai-page-candidates-20261018.sql';
$migration=@file_get_contents($path);
if(!is_string($migration)||!str_contains($migration,'CREATE TABLE IF NOT EXISTS ep_ai_page_candidates')||
   preg_match('/\b(DROP|TRUNCATE|DELETE|UPDATE|INSERT|ALTER)\b/i',$migration)){
    fwrite(STDERR,"BLOCKED: migration source not found or outside permitted create-only contract.\n");
    exit(4);
}
try{
    $db->exec($migration);
    $statement=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $statement->execute(['ep_ai_page_candidates']);
    if((int)$statement->fetchColumn()!==1)throw new \RuntimeException('Schema not present after execution');
    echo "Staging candidate table is present. AI remains disabled; validate Python, worker and tenant isolation before enabling it.\n";
}catch(\Throwable){
    fwrite(STDERR,"Migration failed. Inspect staging database logs; do not enable AI.\n");
    exit(5);
}
