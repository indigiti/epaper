<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\Config;

/**
 * Staging-only, OWNER/ADMIN-triggered private wheel provisioning.
 *
 * This is NOT a generic package manager or shell runner. Versions/index/paths
 * are fixed in code, and no data from request parameters becomes a command.
 * Pip wheels may require outbound HTTPS, disk, and Cloudways Python/pip support.
 */
final class AiClippingDependencies
{
    private const NUMPY='numpy==1.26.4';
    private const OPENCV='opencv-python-headless==4.10.0.84';
    private const MAX_SECONDS=100.0;

    private static function privateRoot(): string
    {
        return dirname(__DIR__,2).'/storage/private';
    }

    /** Resolve only the app-private, server-configured wheel directory. */
    public static function libraryDirectory(): ?string
    {
        $root=self::privateRoot();
        $runtimeFile=dirname(__DIR__,2).'/config/runtime.php';
        $configured=(string)Config::get('ai_clipping.python_lib_dir','');
        if(is_file($runtimeFile)){
            try{
                if(function_exists('opcache_invalidate'))@opcache_invalidate($runtimeFile,true);
                $runtime=require $runtimeFile;
                if(is_array($runtime)&&isset($runtime['ai_clipping']['python_lib_dir'])){
                    $configured=(string)$runtime['ai_clipping']['python_lib_dir'];
                }
            }catch(\Throwable){
                return null;
            }
        }
        $realRoot=realpath($root);
        $real=$configured!==''?realpath($configured):false;
        if(!$realRoot||!$real||!is_dir($real)||!str_starts_with($real,$realRoot.DIRECTORY_SEPARATOR)){
            return null;
        }
        return $real;
    }

    /** Explicitly managed private Python modules, never global pip installs. */
    public static function environment(?string $override=null): array
    {
        $env=getenv();
        if(!is_array($env))$env=[];
        $env['PYTHONNOUSERSITE']='1';
        $lib=$override??self::libraryDirectory();
        if(is_string($lib)&&$lib!==''){
            $realRoot=realpath(self::privateRoot());
            $real=realpath($lib);
            if($realRoot&&$real&&is_dir($real)&&str_starts_with($real,$realRoot.DIRECTORY_SEPARATOR)){
                $env['PYTHONPATH']=$real;
            }
        }
        return $env;
    }

    public static function install(bool $confirmed): string
    {
        if(!AiClippingWebSetup::gate()['allowed'])throw new \RuntimeException('STAGING_ONLY');
        if(!$confirmed)throw new \RuntimeException('INSTALL_CONFIRMATION_REQUIRED');
        if(AiCandidatePipeline::enabled())throw new \RuntimeException('DISABLE_AI_BEFORE_INSTALL');
        if(!function_exists('proc_open'))throw new \RuntimeException('WORKER_PROCESS_UNAVAILABLE');

        $root=dirname(__DIR__,2);
        $private=self::privateRoot();
        if(!is_dir($private)&&!@mkdir($private,0700,true)&&!is_dir($private)){
            throw new \RuntimeException('PYTHON_LIB_STORAGE_UNAVAILABLE');
        }
        $realRoot=realpath($root);
        $realPrivate=realpath($private);
        if(!$realRoot||!$realPrivate||
            !str_starts_with($realPrivate,$realRoot.DIRECTORY_SEPARATOR)||
            !is_writable($realPrivate))throw new \RuntimeException('PYTHON_LIB_STORAGE_UNAVAILABLE');

        $lock=@fopen($realPrivate.'/.ai-dependencies.lock','c');
        if(!$lock)throw new \RuntimeException('PYTHON_LIB_STORAGE_UNAVAILABLE');
        if(!@flock($lock,LOCK_EX|LOCK_NB)){
            @fclose($lock);
            throw new \RuntimeException('PYTHON_INSTALL_ALREADY_RUNNING');
        }
        $temp='';
        try{
            $paths=AiClippingActivation::availableInterpreters();
            if(!$paths)throw new \RuntimeException('PYTHON_NOT_CONFIGURED');
            $python=$paths[0];
            // For NumPy 1.26.4 and OpenCV 4.10.0 pinned wheels, do not support
            // Python 3.13+; preflight will issue a sanitized compatibility error.
            $status=self::run([$python,'-c',
                'import sys; sys.exit(0 if (3,9)<=sys.version_info[:2]<=(3,12) else 1)'],
                $root,10.0);
            if($status!==0)throw new \RuntimeException('PYTHON_VERSION_UNSUPPORTED');

            $temp=$realPrivate.'/ai-deps-'.bin2hex(random_bytes(7));
            if(!@mkdir($temp,0700))throw new \RuntimeException('PYTHON_LIB_STORAGE_UNAVAILABLE');
            $command=[
                $python,'-m','pip','--isolated','install',
                '--disable-pip-version-check','--no-input','--no-cache-dir',
                '--only-binary=:all:','--no-deps',
                '--timeout','12','--retries','1',
                '--index-url','https://pypi.org/simple',
                '--target',$temp,
                self::NUMPY,self::OPENCV,
            ];
            $result=self::run($command,$root,self::MAX_SECONDS,self::environment());
            if($result===-2)throw new \RuntimeException('PYTHON_INSTALL_TIMEOUT');
            if($result!==0){
                // Short local check to distinguish absent pip from unavailable
                // wheel download / network / ABI, without disclosing paths.
                $pip=self::run([$python,'-m','pip','--version'],$root,5.0);
                if($pip!==0)throw new \RuntimeException('PYTHON_PIP_MISSING');
                throw new \RuntimeException('PYTHON_WHEEL_INSTALL_FAILED');
            }

            if(AiClippingActivation::probeStaged($python,$temp)!=='PASS'){
                throw new \RuntimeException('PYTHON_WHEEL_VALIDATION_FAILED');
            }

            // Stage & verify complete BEFORE changing private runtime.
            AiClippingActivation::saveDependencyConfig($python,$temp);
            try{
                AiClippingActivation::verifyPython();
            }catch(\Throwable){
                throw new \RuntimeException('PYTHON_WHEEL_VALIDATION_FAILED');
            }
            // Retain versioned private libraries used by a running worker.
            // Never rename or delete another active virtualenv/installation.
            $temp='';
            return 'PYTHON_DEPENDENCIES_INSTALLED';
        }finally{
            if($temp!==''&&is_dir($temp))self::cleanup($temp);
            @flock($lock,LOCK_UN);
            @fclose($lock);
        }
    }

    private static function run(array $command,string $cwd,float $maxSeconds,?array $env=null): int
    {
        $pipes=[];
        try{
            $process=@proc_open($command,
                [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],
                $pipes,$cwd,$env);
        }catch(\Throwable){return -1;}
        if(!is_resource($process))return -1;
        $exit=-1;$deadline=microtime(true)+$maxSeconds;$timedOut=false;
        try{
            stream_set_blocking($pipes[1],false);
            stream_set_blocking($pipes[2],false);
            while(true){
                @stream_get_contents($pipes[1]);
                @stream_get_contents($pipes[2]);
                $status=@proc_get_status($process);
                if(!$status||!($status['running']??false)){
                    $exit=(int)($status['exitcode']??-1);
                    break;
                }
                if(microtime(true)>$deadline){
                    $timedOut=true;
                    @proc_terminate($process,15);
                    usleep(200000);
                    $status=@proc_get_status($process);
                    if($status&&($status['running']??false))@proc_terminate($process,9);
                    break;
                }
                usleep(100000);
            }
        }finally{
            foreach($pipes as $pipe)if(is_resource($pipe))@fclose($pipe);
            $closed=@proc_close($process);
            if($exit===-1&&$closed>=0)$exit=$closed;
        }
        return $timedOut?-2:$exit;
    }

    private static function cleanup(string $path): void
    {
        $root=realpath(self::privateRoot());
        $target=realpath($path);
        if(!$root||!$target||!str_starts_with($target,$root.'/ai-deps-'))return;
        try{
            $entries=new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($target,\FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST);
            foreach($entries as $entry){
                if($entry->isDir()&&!$entry->isLink())@rmdir($entry->getPathname());
                else @unlink($entry->getPathname());
            }
            @rmdir($target);
        }catch(\Throwable){}
    }
}
