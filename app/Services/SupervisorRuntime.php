<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,RedisStore};

/**
 * Safe Supervisor readiness/installation helper.
 *
 * It never starts or stops operating-system processes. Persistent process
 * management stays outside the PHP/FPM security boundary. The service only
 * diagnoses runtime prerequisites and generates credential-free Supervisor
 * configuration using the actual deployed application path.
 */
final class SupervisorRuntime
{
    public static function appRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function phpCli(): array
    {
        $path = '';
        $version = null;
        $pcntl = null;
        $exec = function_exists('exec');
        $found = false;

        if ($exec) {
            $out = [];
            $code = 127;
            try {
                exec('command -v php 2>/dev/null', $out, $code);
                if ($code === 0 && !empty($out[0])) {$path = trim((string)$out[0]);$found = true;}
            } catch (\Throwable) {}

            if ($path !== '') {
                $out = [];
                $code = 1;
                try {
                    exec(escapeshellarg($path) . ' -r ' . escapeshellarg('echo PHP_VERSION,"|",(function_exists("pcntl_signal")?"1":"0");') . ' 2>/dev/null', $out, $code);
                    if ($code === 0 && !empty($out[0])) {
                        [$versionRaw, $pcntlRaw] = array_pad(explode('|', trim((string)$out[0]), 2), 2, '');
                        $version = $versionRaw !== '' ? $versionRaw : null;
                        $pcntl = $pcntlRaw === '1';
                    }
                } catch (\Throwable) {}
            }
        }

        if ($path === '') $path = 'php';
        return ['path'=>$path,'version'=>$version,'pcntl'=>$pcntl,'exec_available'=>$exec,'found'=>$found];
    }

    public static function expected(): array
    {
        return [
            'outbox'=>max(1,(int)Config::get('queue.expected_workers.outbox',1)),
            'render'=>max(1,(int)Config::get('queue.expected_workers.render',1)),
            'assets'=>max(1,(int)Config::get('queue.expected_workers.assets',2)),
            'ocr'=>max(1,(int)Config::get('queue.expected_workers.ocr',1)),
            'finalize'=>max(1,(int)Config::get('queue.expected_workers.finalize',1)),
        ];
    }

    public static function commands(): array
    {
        $root = self::appRoot();
        $php = self::phpCli()['path'];
        $maxJobs = max(1,(int)Config::get('queue.worker_max_jobs',250));
        return [
            'outbox'=>$php.' '.$root.'/bin/outbox-publisher.php',
            'render'=>$php.' '.$root.'/bin/rabbit-worker.php --lane=render --max-jobs='.$maxJobs,
            'assets'=>$php.' '.$root.'/bin/rabbit-worker.php --lane=assets --max-jobs='.$maxJobs,
            'ocr'=>$php.' '.$root.'/bin/rabbit-worker.php --lane=ocr --max-jobs='.$maxJobs,
            'finalize'=>$php.' '.$root.'/bin/rabbit-worker.php --lane=finalize --max-jobs='.$maxJobs,
        ];
    }

    public static function preflight(): array
    {
        $root = self::appRoot();
        $php = self::phpCli();
        $checks = [];
        $add = static function(string $key, bool $ok, string $detail, bool $required=true) use (&$checks): void {
            $checks[$key] = ['ok'=>$ok,'detail'=>$detail,'required'=>$required];
        };

        $add('Application root', is_dir($root), is_dir($root)?$root:'Application root is missing');
        $add('Outbox command', is_file($root.'/bin/outbox-publisher.php'), $root.'/bin/outbox-publisher.php');
        $add('Rabbit worker command', is_file($root.'/bin/rabbit-worker.php'), $root.'/bin/rabbit-worker.php');
        $logDir=$root.'/storage/logs';$logCreated=false;if(!is_dir($logDir)&&is_dir($root.'/storage')&&is_writable($root.'/storage')){$logCreated=@mkdir($logDir,0775,true);}
        $logOk=is_dir($logDir)&&is_writable($logDir);
        $add('Storage logs', $logOk, $logDir.($logOk?($logCreated?' created and writable':' is writable'):' is missing or not writable'));
        $add('Queue driver', QueueService::rabbitEnabled(), QueueService::rabbitEnabled()?'RabbitMQ is active':'Set queue.driver to rabbitmq');
        $add('AMQP client', class_exists('PhpAmqpLib\\Connection\\AMQPStreamConnection'), class_exists('PhpAmqpLib\\Connection\\AMQPStreamConnection')?'php-amqplib installed':'Run composer install --no-dev --optimize-autoloader');

        $rabbit = RabbitMq::instance()->ping();
        $add('RabbitMQ', (bool)($rabbit['ok']??false), (string)($rabbit['detail']??'Connection unavailable'));

        $redisOk = false;
        if (RedisStore::enabled()) {
            try { $redisOk = RedisStore::client()?->ping() !== false; } catch (\Throwable) { $redisOk = false; }
        }
        $add('Redis', $redisOk, $redisOk?'Connected; worker heartbeats use Redis':'Redis unavailable; MariaDB heartbeat fallback will be used', false);
        $add('PHP CLI', (bool)($php['found']??false), ($php['version']?'PHP '.$php['version'].' · ':'').$php['path'].(($php['found']??false)?'':' · command not verified'), true);
        $pcntlDetail = $php['pcntl']===null?'Could not inspect CLI PCNTL':($php['pcntl']?'Available':'Unavailable; long RabbitMQ callbacks lose signal-based broker heartbeat support');
        $add('CLI PCNTL', $php['pcntl']!==false, $pcntlDetail, false);

        $requiredOk = true;
        foreach ($checks as $c) if (($c['required']??true) && !($c['ok']??false)) $requiredOk = false;
        return ['ok'=>$requiredOk,'checks'=>$checks,'php'=>$php,'root'=>$root];
    }

    public static function diagnosis(): array
    {
        $expected = self::expected();
        $workers = InfrastructureConfig::workerStatus();
        $stats = RabbitMq::instance()->queueStats();
        $lanes = [];
        foreach ($expected as $lane=>$count) {
            $heartbeat = (int)($workers['live'][$lane]??0);
            $consumers = null;
            if ($lane !== 'outbox') {
                try {
                    $queue = RabbitMq::instance()->queueForLane($lane);
                    $consumers = (int)($stats[$queue]['consumers']??0);
                } catch (\Throwable) { $consumers = 0; }
            }

            if ($lane === 'outbox') {
                $status = $heartbeat >= $count ? 'HEALTHY' : ($heartbeat > 0 ? 'PARTIAL' : 'NOT_RUNNING');
            } elseif ($heartbeat >= $count && $consumers >= $count) {
                $status = 'HEALTHY';
            } elseif ($consumers >= $count && $heartbeat < $count) {
                $status = 'HEARTBEAT_MISSING';
            } elseif ($heartbeat >= $count && $consumers < $count) {
                $status = 'BROKER_CONSUMER_MISSING';
            } elseif ($heartbeat > 0 || $consumers > 0) {
                $status = 'PARTIAL';
            } else {
                $status = 'NOT_RUNNING';
            }

            $message = match ($status) {
                'HEALTHY' => 'Worker process and runtime heartbeat are healthy.',
                'HEARTBEAT_MISSING' => 'RabbitMQ sees consumers, but the current application heartbeat is missing. Restart workers so they load the current release/config.',
                'BROKER_CONSUMER_MISSING' => 'A heartbeat is present but RabbitMQ does not see enough consumers. Restart this worker lane and verify broker connectivity.',
                'PARTIAL' => 'Only part of the configured worker capacity is live.',
                default => 'No live worker detected. Create/start this Supervisor program using the generated command.',
            };
            $lanes[$lane] = ['status'=>$status,'expected'=>$count,'heartbeat'=>$heartbeat,'consumers'=>$consumers,'message'=>$message];
        }
        $ok = !array_filter($lanes, static fn(array $x): bool => $x['status'] !== 'HEALTHY');
        return ['ok'=>$ok,'lanes'=>$lanes,'heartbeat_source'=>$workers['source']??'unknown','queues'=>$stats];
    }

    public static function cliPreflight(): ?array
    {
        $php = self::phpCli();
        if (!($php['exec_available']??false) || !($php['found']??false)) return null;
        $script = self::appRoot().'/bin/supervisor-preflight.php';
        if (!is_file($script)) return null;
        $out=[];$code=1;
        try {
            exec(escapeshellarg((string)$php['path']).' '.escapeshellarg($script).' --json 2>&1',$out,$code);
            $decoded=json_decode(implode("\n",$out),true);
            if(is_array($decoded)){$decoded['exit_code']=$code;return$decoded;}
            return ['ok'=>false,'exit_code'=>$code,'detail'=>substr(implode("\n",$out),0,1000)];
        } catch (\Throwable $e) {
            return ['ok'=>false,'exit_code'=>1,'detail'=>$e->getMessage()];
        }
    }

    public static function config(): string
    {
        $root = self::appRoot();
        $commands = self::commands();
        $expected = self::expected();
        $logs = $root.'/storage/logs';
        $maxBytes='20MB';
        $blocks=[];
        $spec = [
            'outbox'=>['program'=>'epaper-outbox','numprocs'=>$expected['outbox'],'wait'=>30,'log'=>'outbox.log'],
            'render'=>['program'=>'epaper-render','numprocs'=>$expected['render'],'wait'=>60,'log'=>'render-worker.log'],
            'assets'=>['program'=>'epaper-assets','numprocs'=>$expected['assets'],'wait'=>60,'log'=>'assets-worker.log'],
            'ocr'=>['program'=>'epaper-ocr','numprocs'=>$expected['ocr'],'wait'=>360,'log'=>'ocr-worker.log'],
            'finalize'=>['program'=>'epaper-finalize','numprocs'=>$expected['finalize'],'wait'=>60,'log'=>'finalize-worker.log'],
        ];
        foreach($spec as$lane=>$s){
            $lines=[
                '[program:'.$s['program'].']',
                'command='.$commands[$lane],
                'directory='.$root,
                'autostart=true',
                'autorestart=true',
                'startsecs=3',
                'startretries=5',
                'numprocs='.$s['numprocs'],
            ];
            if($s['numprocs']>1)$lines[]='process_name=%(program_name)s_%(process_num)02d';
            array_push($lines,
                'redirect_stderr=true',
                'stdout_logfile='.$logs.'/'.$s['log'],
                'stdout_logfile_maxbytes='.$maxBytes,
                'stdout_logfile_backups=5',
                'stopwaitsecs='.$s['wait'],
                'stopsignal=TERM',
                'stopasgroup=true',
                'killasgroup=true'
            );
            $blocks[]=implode("\n",$lines);
        }
        return '; EP-DI '.trim((string)@file_get_contents($root.'/VERSION'))." — generated Supervisor configuration\n"
            .'; Generated for '.$root.". Contains no RabbitMQ/Redis credentials.\n"
            .'; Add through your hosting/Supervisor control plane, then wait 15 seconds and refresh Infrastructure.\n\n'
            .implode("\n\n",$blocks)."\n";
    }
}
