<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,Database};

/**
 * Browser-owned staging activation; does not publish, enqueue or edit articles.
 * Expensive checks run only on explicit OWNER/ADMIN POST, not on each page load.
 */
final class AiClippingActivation
{
    private const TEST_TTL=86400;

    public static function pythonState(): array
    {
        $path=(string)Config::get('ai_clipping.python_binary','');
        $test=Config::get('ai_clipping.python_validation',[]);
        $test=is_array($test)?$test:[];
        $script=dirname(__DIR__,2).'/bin/ai-clipping-smoke.py';
        $valid=str_starts_with($path,'/')&&is_file($path)&&is_executable($path)
            &&(int)($test['at']??0)>time()-self::TEST_TTL
            &&hash_equals((string)($test['binary_sha256']??''),hash('sha256',$path))
            &&is_file($script)
            &&hash_equals((string)($test['smoke_sha256']??''),hash_file('sha256',$script))
            &&is_file(dirname(__DIR__,2).'/bin/ai-clipping-probe.py')
            &&hash_equals((string)($test['probe_sha256']??''),
                hash_file('sha256',dirname(__DIR__,2).'/bin/ai-clipping-probe.py'))
            &&hash_equals((string)($test['library_sha256']??''),
                hash('sha256',(string)(AiClippingDependencies::libraryDirectory()??'')));
        return ['verified'=>$valid,'configured'=>str_starts_with($path,'/')&&is_executable($path)];
    }

    /** @return string[] fixed interpreter candidates, never accepted from browser input. */
    public static function availableInterpreters(): array
    {
        $root=dirname(__DIR__,2);
        $configured=trim((string)Config::get('ai_clipping.python_binary',''));
        $candidates=[$configured,$root.'/storage/private/ai-venv/bin/python',
            '/usr/local/bin/python3.12','/usr/bin/python3.12','/usr/local/bin/python3',
            '/usr/bin/python3'];
        $list=[];
        foreach($candidates as $path){
            if(!str_starts_with($path,'/')||str_contains($path,'..')||
                !is_file($path)||!is_executable($path))continue;
            $real=realpath($path);
            if($real&&str_starts_with($real,'/')&&is_executable($real))$list[$real]=$real;
        }
        return array_values($list);
    }

    private const PROBE_CODES=[
        'PASS','PYTHON_VERSION_UNSUPPORTED','NUMPY_MISSING','NUMPY_IMPORT_FAILED',
        'OPENCV_MISSING','OPENCV_IMPORT_FAILED','TEMP_STORAGE_UNAVAILABLE',
        'IMAGE_FIXTURE_FAILED','CANDIDATE_GENERATOR_FAILED','CANDIDATE_CONTRACT_FAILED',
        'CANDIDATE_TEST_TIMED_OUT','CANDIDATE_PROCESS_UNAVAILABLE',
        'SMOKE_TEST_UNAVAILABLE',
    ];

    public static function verifyPython(): string
    {
        if(!AiClippingWebSetup::gate()['allowed'])throw new \RuntimeException('STAGING_ONLY');
        $root=dirname(__DIR__,2);
        $script=$root.'/bin/ai-clipping-probe.py';
        $smoke=$root.'/bin/ai-clipping-smoke.py';
        if(!is_file($script)||!is_file($smoke))throw new \RuntimeException('SMOKE_TEST_UNAVAILABLE');
        if(!function_exists('proc_open'))throw new \RuntimeException('WORKER_PROCESS_UNAVAILABLE');
        $executables=self::availableInterpreters();
        if(!$executables)throw new \RuntimeException('PYTHON_NOT_CONFIGURED');
        $diagnostic='PYTHON_SMOKE_FAILED';
        $start=microtime(true);
        // Try configured, private and system interpreters. Fixed paths only, no shell.
        foreach(array_slice($executables,0,6) as $path){
            $remaining=24.0-(microtime(true)-$start);
            if($remaining<2.0)break;
            $result=self::probeInterpreter($path,$script,$root,min(12.0,$remaining));
            if($result==='PASS'){
                self::writeAi([
                    'python_binary'=>$path,
                    'python_validation'=>[
                        'at'=>time(),
                        'binary_sha256'=>hash('sha256',$path),
                        'smoke_sha256'=>hash_file('sha256',$smoke),
                        'probe_sha256'=>hash_file('sha256',$script),
                        'library_sha256'=>hash('sha256',(string)(AiClippingDependencies::libraryDirectory()??'')),
                    ],
                ]);
                return 'PYTHON_SMOKE_PASSED';
            }
            // A known dependency or generator failure is more useful than
            // a later generic startup error. Never expose arbitrary output.
            if($diagnostic==='PYTHON_SMOKE_FAILED'||$diagnostic==='CANDIDATE_PROCESS_UNAVAILABLE'){
                $diagnostic=$result;
            }
        }
        throw new \RuntimeException($diagnostic);
    }

    /**
     * Only a single allowlisted status token may leave the worker process.
     * No Python traceback, file contents, paths or environment are shown.
     */
    private static function probeInterpreter(string $binary,string $script,string $root,float $timeout,?string $libraryOverride=null): string
    {
        $pipes=[];
        try {
            $proc=@proc_open([$binary,$script],
                [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],
                $pipes,$root,AiClippingDependencies::environment($libraryOverride));
        }catch(\Throwable){
            return 'CANDIDATE_PROCESS_UNAVAILABLE';
        }
        if(!is_resource($proc))return 'CANDIDATE_PROCESS_UNAVAILABLE';
        $exit=-1;$stdout='';$started=microtime(true);$timedOut=false;
        try{
            stream_set_blocking($pipes[1],false);
            stream_set_blocking($pipes[2],false);
            while(true){
                $stdout=substr($stdout.(string)@stream_get_contents($pipes[1]),-512);
                @stream_get_contents($pipes[2]); // discard private diagnostics
                $status=@proc_get_status($proc);
                if(!$status||!($status['running']??false)){
                    $exit=(int)($status['exitcode']??-1);
                    break;
                }
                if(microtime(true)-$started>$timeout){
                    $timedOut=true;
                    @proc_terminate($proc,15);
                    usleep(150000);
                    $status=@proc_get_status($proc);
                    if($status&&($status['running']??false))@proc_terminate($proc,9);
                    break;
                }
                usleep(100000);
            }
        }finally{
            foreach($pipes as $pipe)if(is_resource($pipe))@fclose($pipe);
            $closed=@proc_close($proc);
            if($exit===-1&&$closed>=0)$exit=$closed;
        }
        if($timedOut)return 'CANDIDATE_TEST_TIMED_OUT';
        if(preg_match('/(?:^|\\n)EPDI_AI_PROBE:([A-Z_]+)(?:\\r?\\n|$)/',$stdout,$m)
            &&in_array($m[1],self::PROBE_CODES,true)){
            return $exit===0&&$m[1]==='PASS'?'PASS':($m[1]==='PASS'?'PYTHON_SMOKE_FAILED':$m[1]);
        }
        return 'PYTHON_SMOKE_FAILED';
    }

    /** Run a private synthetic probe before any runtime change. */
    public static function probeStaged(string $binary,string $directory): string
    {
        if(!AiClippingWebSetup::gate()['allowed'])throw new \RuntimeException('STAGING_ONLY');
        return self::probeInterpreter($binary,
            dirname(__DIR__,2).'/bin/ai-clipping-probe.py',
            dirname(__DIR__,2),12.0,$directory);
    }

    /** Installed Python and libs are only adopted after a passing staged probe. */
    public static function saveDependencyConfig(string $binary,string $directory): void
    {
        if(!AiClippingWebSetup::gate()['allowed'])throw new \RuntimeException('STAGING_ONLY');
        $real=realpath($directory);
        $root=realpath(dirname(__DIR__,2).'/storage/private');
        if(!$real||!$root||!str_starts_with($real,$root.DIRECTORY_SEPARATOR)){
            throw new \RuntimeException('PYTHON_LIB_STORAGE_UNAVAILABLE');
        }
        self::writeAi(['python_binary'=>$binary,'python_lib_dir'=>$real,
            'python_validation'=>[]]);
    }

    /** Used by pre-existing long-lived workers, which may have a cached Config. */
    public static function currentPythonBinary(): string
    {
        $runtime=dirname(__DIR__,2).'/config/runtime.php';
        if(is_file($runtime)){
            try {
                if(function_exists('opcache_invalidate'))@opcache_invalidate($runtime,true);
                $data=require $runtime;
                $bin=is_array($data)?($data['ai_clipping']['python_binary']??''):null;
                if(is_string($bin)&&str_starts_with($bin,'/')&&is_file($bin)&&is_executable($bin))
                    return $bin;
            }catch(\Throwable){}
        }
        return trim((string)Config::get('ai_clipping.python_binary','python3'));
    }

    public static function workerState(bool $activeProbe=false): array
    {
        if(!QueueService::rabbitEnabled())
            return ['ready'=>false,'reason'=>'RABBITMQ_NOT_CONFIGURED'];
        if(!$activeProbe)return ['ready'=>false,'reason'=>'NOT_TESTED'];
        try {
            $ping=RabbitMq::instance()->ping();
            if(!($ping['ok']??false))
                return ['ready'=>false,'reason'=>'RABBITMQ_UNAVAILABLE'];
            $status=InfrastructureConfig::workerStatus();
            if(($status['live']['ocr']??0)<1)
                return ['ready'=>false,'reason'=>'OCR_WORKER_NOT_RUNNING'];
            return ['ready'=>true,'reason'=>'OCR_WORKER_READY'];
        }catch(\Throwable){
            return ['ready'=>false,'reason'=>'RABBITMQ_UNAVAILABLE'];
        }
    }

    public static function enable(bool $confirmed): string
    {
        if(!AiClippingWebSetup::gate()['allowed'])throw new \RuntimeException('STAGING_ONLY');
        if(!$confirmed)throw new \RuntimeException('ACTIVATION_CONFIRMATION_REQUIRED');
        $db=Database::pdo();
        $table=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $table->execute(['ep_ai_page_candidates']);
        if((int)$table->fetchColumn()!==1)throw new \RuntimeException('CANDIDATE_SCHEMA_MISSING');
        if(!is_file(dirname(__DIR__,2).'/bin/ai-candidate-generate.py'))
            throw new \RuntimeException('AI_SOURCE_MISSING');
        if(!self::pythonState()['verified']){
            // Run and persist a real synthetic smoke on this installation first.
            self::verifyPython();
        }
        $worker=self::workerState(true);
        if(!$worker['ready'])throw new \RuntimeException($worker['reason']);
        self::writeAi(['enabled'=>true]);
        return 'AI_DETECTION_ENABLED';
    }

    public static function disable(): string
    {
        // Emergency stop is available even if the worker/database has degraded.
        self::writeAi(['enabled'=>false]);
        return 'AI_DETECTION_DISABLED';
    }

    /** Atomic, narrow update of private AI runtime fields; no DB/Platform writes. */
    private static function writeAi(array $fields): void
    {
        $root=dirname(__DIR__,2);
        $file=$root.'/config/runtime.php';
        if(is_link($file)||!is_dir(dirname($file))||!is_writable(dirname($file))){
            throw new \RuntimeException('PRIVATE_RUNTIME_NOT_WRITABLE');
        }
        $runtime=[];
        if(is_file($file)){
            $runtime=require $file;
            if(!is_array($runtime))throw new \RuntimeException('PRIVATE_RUNTIME_INVALID');
        }
        if(!is_array($runtime['ai_clipping']??null))$runtime['ai_clipping']=[];
        foreach($fields as $key=>$value)$runtime['ai_clipping'][$key]=$value;
        $tmp=tempnam(dirname($file),'.ai-activate-');
        if($tmp===false)throw new \RuntimeException('PRIVATE_RUNTIME_NOT_WRITABLE');
        try{
            $payload="<?php\n// Private ePaper runtime configuration.\nreturn "
                .var_export($runtime,true).";\n";
            if(file_put_contents($tmp,$payload,LOCK_EX)===false)
                throw new \RuntimeException('PRIVATE_RUNTIME_NOT_WRITABLE');
            @chmod($tmp,0600);
            if(!rename($tmp,$file))throw new \RuntimeException('PRIVATE_RUNTIME_NOT_WRITABLE');
            @chmod($file,0600);
        }finally {
            if(is_file($tmp))@unlink($tmp);
        }
    }
}
