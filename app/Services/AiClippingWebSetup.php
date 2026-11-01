<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,Database,Http};

/**
 * Narrow, server-side browser actions for isolated STAGING only.
 * Never mutates live DBs or launches production OCR workers.
 */
final class AiClippingWebSetup
{
    /**
     * An ePaper hostname is not a production/staging signal. For an existing
     * staging installation, the owner explicitly attests the hostname, DB and
     * backup once; this never turns AI processing on automatically.
     */
    public static function gate(): array
    {
        $host=strtolower((string)(parse_url((string)Config::get('app.url',''),PHP_URL_HOST)?:''));
        $requestHost='';
        try{$requestHost=Http::host();}catch(\Throwable){}
        $hostMatches=$host!==''&&$host===$requestHost&&
            !in_array($host,['platform.digiti.in','localhost','127.0.0.1'],true);
        $dbName=(string)Config::get('db.name','');
        $actual=null;
        try{$actual=Database::pdo()->query('SELECT DATABASE()')->fetchColumn();}catch(\Throwable){}
        $dbMatches=$dbName!==''&&is_string($actual)&&hash_equals($dbName,$actual);
        $web=Config::get('ai_clipping.web_setup',[]);
        $web=is_array($web)?$web:[];
        $attested=($web['staging_declared']??false)===true
            &&hash_equals((string)($web['host_sha256']??''),hash('sha256',$host))
            &&hash_equals((string)($web['db_sha256']??''),hash('sha256',$dbName));
        // Dedicated staging host remains supported without extra setup.
        $isolated=strtolower((string)Config::get('app.environment',''))==='staging'
            &&$host!=='epaper.digiti.in'
            &&trim((string)Config::get('platform.default_tenant_guid',''))===''
            &&trim((string)Config::get('platform.default_organisation_guid',''))==='';
        return [
            'allowed'=>$hostMatches&&$dbMatches&&($attested||$isolated),
            'staging'=>$attested||$isolated,
            'host_safe'=>$hostMatches,
            'platform_isolated'=>trim((string)Config::get('platform.default_tenant_guid',''))===''
                &&trim((string)Config::get('platform.default_organisation_guid',''))==='',
            'database_match'=>$dbMatches,
            'existing_staging_confirmed'=>$attested,
            'needs_confirmation'=>!$attested&&!$isolated,
        ];
    }

    /**
     * Record an OWNER/ADMIN attestation in existing private runtime config.
     * This does not change app.environment, queue credentials, tenant routing,
     * activate AI, execute SQL, or create a new Cloudways environment.
     */
    public static function confirmExistingStaging(
        string $typedHost,string $typedDb,bool $backupConfirmed,bool $stagingConfirmed,string $actor
    ): string {
        $gate=self::gate();
        $host=strtolower((string)(parse_url((string)Config::get('app.url',''),PHP_URL_HOST)?:''));
        $dbName=(string)Config::get('db.name','');
        if(!$gate['host_safe']||!$gate['database_match']||
            !$backupConfirmed||!$stagingConfirmed||
            !hash_equals($host,strtolower(trim($typedHost)))||
            !hash_equals($dbName,trim($typedDb))||
            !\EpaperSaas\Core\Uuid::isValid($actor)) {
            throw new \RuntimeException('STAGING_CONFIRMATION_REQUIRED');
        }
        $root=dirname(__DIR__,2);
        $file=$root.'/config/runtime.php';
        if(!is_dir(dirname($file))||!is_writable(dirname($file))||is_link($file)){
            throw new \RuntimeException('PRIVATE_RUNTIME_NOT_WRITABLE');
        }
        $runtime=[];
        if(is_file($file)){
            $runtime=require $file;
            if(!is_array($runtime))throw new \RuntimeException('PRIVATE_RUNTIME_INVALID');
        }
        if(!is_array($runtime['ai_clipping']??null))$runtime['ai_clipping']=[];
        $runtime['ai_clipping']['web_setup']=[
            'staging_declared'=>true,
            'host_sha256'=>hash('sha256',$host),
            'db_sha256'=>hash('sha256',$dbName),
            'confirmed_by'=>$actor,
            'confirmed_at'=>gmdate('c'),
        ];
        // Same private atomic-write convention as InfrastructureConfig.
        $tmp=tempnam(dirname($file),'.ai-stage-');
        if($tmp===false)throw new \RuntimeException('PRIVATE_RUNTIME_NOT_WRITABLE');
        try {
            $body="<?php\n// Private ePaper runtime configuration.\nreturn ".var_export($runtime,true).";\n";
            if(file_put_contents($tmp,$body,LOCK_EX)===false){
                throw new \RuntimeException('PRIVATE_RUNTIME_NOT_WRITABLE');
            }
            @chmod($tmp,0600);
            if(!rename($tmp,$file)){
                throw new \RuntimeException('PRIVATE_RUNTIME_NOT_WRITABLE');
            }
            @chmod($file,0600);
        }finally {
            if(is_file($tmp))@unlink($tmp);
        }
        return 'EXISTING_STAGING_CONFIRMED';
    }

    public static function applyCandidateSchema(string $typedName,bool $confirmed): string
    {
        $gate=self::gate();
        $name=(string)Config::get('db.name','');
        if(!$gate['allowed']||!$confirmed||$name===''||
           !hash_equals($name,trim($typedName))){
            throw new \RuntimeException('STAGING_ONLY_OR_CONFIRMATION_REQUIRED');
        }
        $db=Database::pdo();
        $exists=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $exists->execute(['ep_ai_page_candidates']);
        if((int)$exists->fetchColumn()===1)return 'ALREADY_PRESENT';
        $path=dirname(__DIR__,2).'/database/upgrade-ai-page-candidates-20261018.sql';
        $sql=@file_get_contents($path);
        if(!is_string($sql)||
           !preg_match('/^\s*--.*?CREATE TABLE IF NOT EXISTS ep_ai_page_candidates\s*\(/s',$sql)||
           preg_match('/\b(DROP|DELETE|TRUNCATE|ALTER|UPDATE|INSERT|REPLACE|GRANT)\b/i',$sql)){
            throw new \RuntimeException('MIGRATION_SOURCE_INVALID');
        }
        // Additive table creation only; never run on a canonical production hostname.
        $db->exec($sql);
        $exists->execute(['ep_ai_page_candidates']);
        if((int)$exists->fetchColumn()!==1)throw new \RuntimeException('MIGRATION_VERIFICATION_FAILED');
        return 'SCHEMA_CREATED';
    }

    public static function testPython(): string
    {
        return AiClippingActivation::verifyPython();
    }
}
