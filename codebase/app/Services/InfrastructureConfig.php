<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,RedisStore,RequestContext,Store,Uuid};

final class InfrastructureConfig
{
    public static function runtimeFile(): string
    {
        return dirname(__DIR__, 2) . '/config/runtime.php';
    }

    public static function runtimeWritable(): bool
    {
        $file = self::runtimeFile();
        return is_file($file) ? is_writable($file) : is_writable(dirname($file));
    }

    public static function saveRabbit(array $input): void
    {
        if (!self::runtimeWritable()) throw new \RuntimeException('config directory is not writable by PHP');
        $driver = strtolower(trim((string)($input['driver'] ?? 'rabbitmq')));
        if (!in_array($driver, ['rabbitmq','database'], true)) throw new \InvalidArgumentException('Queue driver must be rabbitmq or database');
        $host = trim((string)($input['host'] ?? '127.0.0.1'));
        $port = max(1, min(65535, (int)($input['port'] ?? 5672)));
        $username = trim((string)($input['username'] ?? ''));
        $vhost = trim((string)($input['vhost'] ?? '/')) ?: '/';
        if ($driver === 'rabbitmq' && ($host === '' || $username === '')) throw new \InvalidArgumentException('RabbitMQ host and username are required');

        $currentPassword = (string)Config::get('queue.rabbitmq.password', '');
        $password = array_key_exists('password', $input) && (string)$input['password'] !== '' ? (string)$input['password'] : $currentPassword;
        if ($driver === 'rabbitmq' && $password === '') throw new \InvalidArgumentException('RabbitMQ password is required');

        $runtime = self::loadRuntime();
        $runtime['queue'] = [
            'driver' => $driver,
            'max_attempts' => [
                'edition_render' => max(1, min(10, (int)($input['max_render_attempts'] ?? Config::get('queue.max_attempts.edition_render', 3)))),
                'page_assets' => max(1, min(10, (int)($input['max_assets_attempts'] ?? Config::get('queue.max_attempts.page_assets', 3)))),
                'page_ocr' => max(1, min(10, (int)($input['max_ocr_attempts'] ?? Config::get('queue.max_attempts.page_ocr', 3)))),
                'edition_finalize' => max(1, min(10, (int)($input['max_finalize_attempts'] ?? Config::get('queue.max_attempts.edition_finalize', 5)))),
            ],
            'expected_workers' => [
                'outbox' => max(1, min(10, (int)($input['workers_outbox'] ?? Config::get('queue.expected_workers.outbox', 1)))),
                'render' => max(1, min(10, (int)($input['workers_render'] ?? Config::get('queue.expected_workers.render', 1)))),
                'assets' => max(1, min(20, (int)($input['workers_assets'] ?? Config::get('queue.expected_workers.assets', 2)))),
                'ocr' => max(1, min(10, (int)($input['workers_ocr'] ?? Config::get('queue.expected_workers.ocr', 1)))),
                'finalize' => max(1, min(10, (int)($input['workers_finalize'] ?? Config::get('queue.expected_workers.finalize', 1)))),
            ],
            'rabbitmq' => [
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password,
                'vhost' => $vhost,
                'connection_timeout' => max(1.0, min(30.0, (float)($input['connection_timeout'] ?? Config::get('queue.rabbitmq.connection_timeout', 3.0)))),
                'read_write_timeout' => max(10.0, min(300.0, (float)($input['read_write_timeout'] ?? Config::get('queue.rabbitmq.read_write_timeout', 70.0)))),
                'heartbeat' => max(5, min(120, (int)($input['heartbeat'] ?? Config::get('queue.rabbitmq.heartbeat', 30)))),
                'keepalive' => true,
                'confirm_timeout' => max(1.0, min(30.0, (float)($input['confirm_timeout'] ?? Config::get('queue.rabbitmq.confirm_timeout', 5.0)))),
                'exchange' => (string)Config::get('queue.rabbitmq.exchange', 'epaper.jobs'),
                'dead_exchange' => (string)Config::get('queue.rabbitmq.dead_exchange', 'epaper.dead'),
                'dead_queue' => (string)Config::get('queue.rabbitmq.dead_queue', 'epaper.dead'),
                'retry_exchange_prefix' => (string)Config::get('queue.rabbitmq.retry_exchange_prefix', 'epaper.retry.'),
                'queues' => [
                    'render' => (string)Config::get('queue.rabbitmq.queues.render', 'epaper.render'),
                    'assets' => (string)Config::get('queue.rabbitmq.queues.assets', 'epaper.assets'),
                    'ocr' => (string)Config::get('queue.rabbitmq.queues.ocr', 'epaper.ocr'),
                    'finalize' => (string)Config::get('queue.rabbitmq.queues.finalize', 'epaper.finalize'),
                ],
            ],
        ];
        self::atomicWrite($runtime);
    }

    public static function saveMedia(array $input): void
    {
        if (!self::runtimeWritable()) throw new \RuntimeException('config directory is not writable by PHP');
        $runtime=self::loadRuntime();
        $runtime['media']=[
            'token_bucket_seconds'=>max(60,min(3600,(int)($input['token_bucket_seconds']??Config::get('media.token_bucket_seconds',300)))),
            'x_accel_prefix'=>trim((string)($input['x_accel_prefix']??Config::get('media.x_accel_prefix',''))),
        ];
        self::atomicWrite($runtime);
    }

    public static function testRabbit(array $input = []): array
    {
        if (!class_exists('PhpAmqpLib\\Connection\\AMQPStreamConnection')) return ['ok'=>false,'detail'=>'php-amqplib is not installed'];
        $host = trim((string)($input['host'] ?? Config::get('queue.rabbitmq.host', '127.0.0.1')));
        $port = (int)($input['port'] ?? Config::get('queue.rabbitmq.port', 5672));
        $username = trim((string)($input['username'] ?? Config::get('queue.rabbitmq.username', '')));
        $password = (string)(($input['password'] ?? '') !== '' ? $input['password'] : Config::get('queue.rabbitmq.password', ''));
        $vhost = trim((string)($input['vhost'] ?? Config::get('queue.rabbitmq.vhost', '/'))) ?: '/';
        $timeout = max(1.0, min(15.0, (float)($input['connection_timeout'] ?? Config::get('queue.rabbitmq.connection_timeout', 3.0))));
        $rw = max(10.0, min(120.0, (float)($input['read_write_timeout'] ?? Config::get('queue.rabbitmq.read_write_timeout', 70.0))));
        $heartbeat = max(5, min(120, (int)($input['heartbeat'] ?? Config::get('queue.rabbitmq.heartbeat', 30))));
        $class='PhpAmqpLib\\Connection\\AMQPStreamConnection';
        try {
            $conn = new $class($host,$port,$username,$password,$vhost,false,'AMQPLAIN',null,'en_US',$timeout,$rw,null,true,$heartbeat);
            $ch = $conn->channel();
            $ch->exchange_declare((string)Config::get('queue.rabbitmq.exchange','epaper.jobs'),'topic',false,true,false);
            $ch->close(); $conn->close();
            return ['ok'=>true,'detail'=>'Connected to '.$host.':'.$port.' vhost='.$vhost];
        } catch (\Throwable $e) {
            return ['ok'=>false,'detail'=>substr($e->getMessage(),0,300)];
        }
    }

    public static function workerStatus(): array
    {
        $expected = [
            'outbox'=>(int)Config::get('queue.expected_workers.outbox',1),
            'render'=>(int)Config::get('queue.expected_workers.render',1),
            'assets'=>(int)Config::get('queue.expected_workers.assets',2),
            'ocr'=>(int)Config::get('queue.expected_workers.ocr',1),
            'finalize'=>(int)Config::get('queue.expected_workers.finalize',1),
        ];
        $live=array_fill_keys(array_keys($expected),0);$details=[];$seen=[];$sources=[];
        foreach(array_keys($expected) as$lane)$seen[$lane]=[];

        // Redis is the primary liveness source because heartbeat keys expire by TTL.
        // Do not return early when Redis is reachable but a lane is empty: supplement
        // with the recent MariaDB heartbeat mirror so transient Redis/prefix changes do
        // not falsely report every Supervisor process as down.
        if(RedisStore::enabled()){
            try{
                $client=RedisStore::client();
                if($client&&$client->ping()!==false){
                    $sources[]='redis';
                    foreach(array_keys($expected) as$lane){
                        $rows=RedisStore::scanJson('worker:heartbeat:v2:'.$lane.':',100);
                        foreach($rows as$payload){
                            $worker=(string)($payload['worker']??'redis:'.$lane.':'.count($seen[$lane]));
                            if(isset($seen[$lane][$worker]))continue;
                            $seen[$lane][$worker]=true;$live[$lane]++;
                            $at=(string)($payload['at']??'');$age=$at!==''?max(0,time()-(int)strtotime($at)):null;
                            $details[]=['lane'=>$lane,'live'=>true,'age_seconds'=>$age,'updated_at'=>$at?:null,'worker'=>$worker,'version'=>$payload['version']??null,'source'=>'redis'];
                        }
                    }
                }
            }catch(\Throwable){}
        }

        // MariaDB is a low-frequency recovery mirror (workers write at most every
        // ~15 seconds). Only recent rows supplement workers not already seen in Redis.
        try {
            $rows=Store::all("SELECT state_key,state_value,updated_at,TIMESTAMPDIFF(SECOND,updated_at,NOW(6)) age_seconds FROM ep_system_state WHERE state_key='queue.outbox_worker' OR state_key LIKE 'queue.worker.%'");
            if($rows)$sources[]='mariadb';
            foreach($rows as$r){
                $key=(string)$r['state_key'];$age=max(0,(int)($r['age_seconds']??999999));$isLive=$age<=45;
                if($key==='queue.outbox_worker'){$lane='outbox';}
                elseif(preg_match('/^queue\.worker\.(render|assets|ocr|finalize)\./',$key,$m)){$lane=$m[1];}
                else continue;
                $payload=json_decode((string)$r['state_value'],true);$worker=is_array($payload)?(string)($payload['worker']??$key):$key;
                if($isLive&&!isset($seen[$lane][$worker])){$seen[$lane][$worker]=true;$live[$lane]++;}
                if(!isset($seen[$lane][$worker])||$isLive)$details[]=['lane'=>$lane,'live'=>$isLive,'age_seconds'=>$age,'updated_at'=>$r['updated_at'],'worker'=>$worker,'version'=>is_array($payload)?($payload['version']??null):null,'source'=>'mariadb'];
            }
        } catch (\Throwable $e) {
            if(!$sources)return ['ok'=>false,'expected'=>$expected,'live'=>$live,'details'=>[],'source'=>'unavailable','error'=>$e->getMessage()];
        }
        $ok=true;foreach($expected as$lane=>$count)if(($live[$lane]??0)<$count)$ok=false;
        $source=$sources?implode('+',array_values(array_unique($sources))):'none';
        return ['ok'=>$ok,'expected'=>$expected,'live'=>$live,'details'=>$details,'source'=>$source];
    }

    public static function createProbe(): string
    {
        $probe=Uuid::v7();$tenant=RequestContext::tenantGuid();
        $lanes=['render'=>'edition.render','assets'=>'page.assets','ocr'=>'page.ocr','finalize'=>'edition.finalize'];
        Store::tx(function()use($probe,$tenant,$lanes){
            foreach($lanes as$lane=>$routing){
                Store::exec(
                    'INSERT INTO ep_outbox_events(outbox_event_guid,tenant_guid,event_type,payload_json,status,attempt_count,available_at,created_at) VALUES(?,?,"QUEUE_PROBE",?,"PENDING",0,NOW(6),NOW(6))',
                    [Uuid::toBinary(Uuid::v7()),Uuid::toBinary($tenant),json_encode(['probe_guid'=>$probe,'tenant_guid'=>$tenant,'lane'=>$lane,'routing_key'=>$routing],JSON_UNESCAPED_SLASHES)]
                );
            }
        });
        return $probe;
    }

    public static function probeStatus(string $probe): array
    {
        if (!Uuid::isValid($probe)) return ['ok'=>false,'complete'=>false,'message'=>'Invalid probe'];
        $lanes=['render','assets','ocr','finalize'];$out=[];$complete=true;
        foreach($lanes as$lane){
            $key='queue.probe.'.$probe.'.'.$lane;
            $row=Store::one('SELECT state_value,updated_at FROM ep_system_state WHERE state_key=?',[$key]);
            if(!$row){$out[$lane]=['ok'=>false,'status'=>'WAITING'];$complete=false;continue;}
            $p=json_decode((string)$row['state_value'],true);$out[$lane]=['ok'=>true,'status'=>'PASS','worker'=>is_array($p)?($p['worker']??null):null,'updated_at'=>$row['updated_at']];
        }
        $pending=(int)Store::value('SELECT COUNT(*) FROM ep_outbox_events WHERE tenant_guid=? AND event_type="QUEUE_PROBE" AND payload_json LIKE ? AND status IN ("PENDING","PROCESSING")',[Uuid::toBinary(RequestContext::tenantGuid()),'%'.$probe.'%']);
        return ['ok'=>$complete,'complete'=>$complete,'outbox_pending'=>$pending,'lanes'=>$out];
    }

    private static function loadRuntime(): array
    {
        $file=self::runtimeFile();if(!is_file($file))return[];$d=require$file;return is_array($d)?$d:[];
    }

    private static function atomicWrite(array $runtime): void
    {
        $file=self::runtimeFile();$tmp=$file.'.tmp.'.getmypid();$body="<?php\n// Generated by ePaper SaaS Infrastructure Dashboard. Keep outside public webroot.\nreturn ".var_export($runtime,true).";\n";
        if(file_put_contents($tmp,$body,LOCK_EX)===false)throw new \RuntimeException('Could not write runtime configuration');
        @chmod($tmp,0600);
        if(!rename($tmp,$file)){@unlink($tmp);throw new \RuntimeException('Could not activate runtime configuration');}
        @chmod($file,0600);
    }
}
