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
    public static function gate(): array
    {
        $host=strtolower((string)(parse_url((string)Config::get('app.url',''),PHP_URL_HOST)?:''));
        $requestHost='';
        try{$requestHost=Http::host();}catch(\Throwable){}
        $staging=strtolower((string)Config::get('app.environment',''))==='staging';
        $hostSafe=$host!==''&&$host===$requestHost&&
            !in_array($host,['epaper.digiti.in','platform.digiti.in','localhost','127.0.0.1'],true);
        $fallbackClear=trim((string)Config::get('platform.default_tenant_guid',''))===''&&
            trim((string)Config::get('platform.default_organisation_guid',''))==='';
        $dbName=(string)Config::get('db.name','');
        $actual=null;
        try{$actual=Database::pdo()->query('SELECT DATABASE()')->fetchColumn();}catch(\Throwable){}
        $dbMatches=$dbName!==''&&is_string($actual)&&hash_equals($dbName,$actual);
        return [
            'allowed'=>$staging&&$hostSafe&&$fallbackClear&&$dbMatches,
            'staging'=>$staging,'host_safe'=>$hostSafe,
            'platform_isolated'=>$fallbackClear,'database_match'=>$dbMatches,
        ];
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
        $gate=self::gate();
        if(!$gate['allowed'])throw new \RuntimeException('STAGING_ONLY');
        $root=dirname(__DIR__,2);
        $python=(string)Config::get('ai_clipping.python_binary','');
        // Worker configured executable only: never accept browser-supplied paths/commands.
        if(!str_starts_with($python,'/')||str_contains($python,'..')||
           !is_file($python)||!is_executable($python)||!function_exists('proc_open')){
            throw new \RuntimeException('PYTHON_NOT_CONFIGURED');
        }
        $script=$root.'/bin/ai-clipping-smoke.py';
        if(!is_file($script))throw new \RuntimeException('SMOKE_TEST_UNAVAILABLE');
        $pipes=[];
        $proc=@proc_open([$python,$script],
            [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
        if(!is_resource($proc))throw new \RuntimeException('WORKER_PROCESS_UNAVAILABLE');
        $exit=null;$started=microtime(true);
        try{
            stream_set_blocking($pipes[1],false);
            stream_set_blocking($pipes[2],false);
            while(true){
                // Suppress document text, interpreter traces and private paths.
                @stream_get_contents($pipes[1]);
                @stream_get_contents($pipes[2]);
                $state=@proc_get_status($proc);
                if(!$state||!($state['running']??false)){
                    $exit=$state['exitcode']??-1;
                    break;
                }
                if(microtime(true)-$started>20){
                    @proc_terminate($proc,15);
                    usleep(200000);
                    $still=@proc_get_status($proc);
                    if($still&&($still['running']??false))@proc_terminate($proc,9);
                    throw new \RuntimeException('WORKER_TEST_TIMED_OUT');
                }
                usleep(100000);
            }
        }finally{
            foreach($pipes as $pipe)if(is_resource($pipe))@fclose($pipe);
            $closed=@proc_close($proc);
            if(($exit===null||$exit===-1)&&$closed>=0)$exit=$closed;
        }
        if($exit!==0)throw new \RuntimeException('PYTHON_SMOKE_FAILED');
        return 'PYTHON_SMOKE_PASSED';
    }
}
