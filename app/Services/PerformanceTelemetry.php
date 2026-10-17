<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,RedisStore,RequestContext,Store};

/**
 * Bounded Redis-backed request + real-user performance telemetry.
 * Stores no request bodies, SQL text, phone/email, cookies or customer content.
 */
final class PerformanceTelemetry
{
    private static float $started=0.0;
    private static bool $registered=false;
    private static ?string $mediaDelivery=null;

    public static function begin(): void
    {
        if(self::$registered)return;
        self::$registered=true;self::$started=microtime(true);
        register_shutdown_function([self::class,'finish']);
    }

    public static function mediaDelivery(string $mode): void
    {
        self::$mediaDelivery=in_array($mode,['x-accel','php'],true)?$mode:null;
    }

    public static function finish(): void
    {
        if(self::$started<=0)return;
        $tenant=(string)RequestContext::get('tenant_guid','');
        if($tenant===''||!RedisStore::enabled())return;
        $sampleRate=(float)Config::get('observability.request_sample_rate',1.0);
        $sampleRate=max(0.0,min(1.0,$sampleRate));
        if($sampleRate<1.0){try{if(random_int(1,10000)>=(int)round($sampleRate*10000)+1)return;}catch(\Throwable){}}
        $db=Store::metrics();$redis=RedisStore::metrics();
        $path=(string)(parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)?:'/');
        $row=[
            'ts'=>time(),
            'route'=>self::routeKey($path),
            'method'=>(string)($_SERVER['REQUEST_METHOD']??'GET'),
            'status'=>http_response_code()?:200,
            'request_ms'=>round((microtime(true)-self::$started)*1000,2),
            'db_queries'=>(int)($db['queries']??0),
            'db_ms'=>(float)($db['query_ms']??0),
            'db_max_ms'=>(float)($db['max_query_ms']??0),
            'redis_reads'=>(int)($redis['reads']??0),
            'redis_hit_rate'=>$redis['hit_rate']??null,
            'session_open'=>session_status()===PHP_SESSION_ACTIVE,
            'media_delivery'=>self::$mediaDelivery,
        ];
        try{
            $c=RedisStore::client();if(!$c)return;
            $key=RedisStore::key('perf:req:'.$tenant);
            $json=json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);if($json===false)return;
            $c->lPush($key,$json);$c->lTrim($key,0,max(49,(int)Config::get('observability.request_sample_limit',499)));$c->expire($key,86400);
        }catch(\Throwable){}
    }

    public static function recordVitals(array $payload): void
    {
        $tenant=(string)RequestContext::get('tenant_guid','');if($tenant===''||!RedisStore::enabled())return;
        $clean=['ts'=>time(),'route'=>self::routeKey((string)($payload['route']??'/'))];
        foreach(['lcp','inp','cls'] as$k){
            if(!isset($payload[$k])||!is_numeric($payload[$k]))continue;
            $v=(float)$payload[$k];
            if($k==='cls'){$v=max(0,min(10,$v));}else{$v=max(0,min(120000,$v));}
            $clean[$k]=round($v,$k==='cls'?4:1);
        }
        if(count($clean)<=2)return;
        try{
            $c=RedisStore::client();if(!$c)return;$key=RedisStore::key('perf:vitals:'.$tenant);
            $json=json_encode($clean,JSON_UNESCAPED_SLASHES);if($json===false)return;
            $c->lPush($key,$json);$c->lTrim($key,0,max(49,(int)Config::get('observability.vitals_sample_limit',499)));$c->expire($key,604800);
        }catch(\Throwable){}
    }

    public static function snapshot(): array
    {
        $tenant=(string)RequestContext::get('tenant_guid','');
        $out=['available'=>false,'samples'=>0,'request'=>[],'routes'=>[],'media'=>['x-accel'=>0,'php'=>0],'vitals'=>['samples'=>0]];
        if($tenant===''||!RedisStore::enabled())return$out;
        try{
            $c=RedisStore::client();if(!$c)return$out;
            $req=self::decodeList($c->lRange(RedisStore::key('perf:req:'.$tenant),0,499)?:[]);
            $vitals=self::decodeList($c->lRange(RedisStore::key('perf:vitals:'.$tenant),0,499)?:[]);
            $out['available']=true;$out['samples']=count($req);
            if($req){
                $request=array_map(fn($r)=>(float)($r['request_ms']??0),$req);$db=array_map(fn($r)=>(float)($r['db_ms']??0),$req);$queries=array_map(fn($r)=>(float)($r['db_queries']??0),$req);
                $out['request']=['p50_ms'=>self::percentile($request,.50),'p95_ms'=>self::percentile($request,.95),'p99_ms'=>self::percentile($request,.99),'avg_db_ms'=>round(array_sum($db)/max(1,count($db)),2),'avg_db_queries'=>round(array_sum($queries)/max(1,count($queries)),1)];
                $groups=[];
                foreach($req as$r){$route=(string)($r['route']??'other');$groups[$route][]=$r;if(($r['media_delivery']??null)==='x-accel')$out['media']['x-accel']++;elseif(($r['media_delivery']??null)==='php')$out['media']['php']++;}
                uasort($groups,fn($a,$b)=>count($b)<=>count($a));
                foreach(array_slice($groups,0,10,true) as$route=>$rows){$ms=array_map(fn($r)=>(float)($r['request_ms']??0),$rows);$qs=array_map(fn($r)=>(float)($r['db_queries']??0),$rows);$out['routes'][]=['route'=>$route,'samples'=>count($rows),'p95_ms'=>self::percentile($ms,.95),'avg_db_queries'=>round(array_sum($qs)/max(1,count($qs)),1)];}
            }
            if($vitals){$out['vitals']['samples']=count($vitals);foreach(['lcp','inp','cls']as$k){$vals=[];foreach($vitals as$r)if(isset($r[$k])&&is_numeric($r[$k]))$vals[]=(float)$r[$k];if($vals)$out['vitals'][$k.'_p75']=self::percentile($vals,.75,$k==='cls'?4:1);}}
        }catch(\Throwable){}
        return$out;
    }

    private static function decodeList(array $raw): array
    {
        $out=[];foreach($raw as$v){$d=json_decode((string)$v,true);if(is_array($d))$out[]=$d;}return$out;
    }

    private static function percentile(array $values,float $p,int $precision=2): float
    {
        if(!$values)return 0.0;sort($values,SORT_NUMERIC);$i=(int)ceil($p*count($values))-1;$i=max(0,min(count($values)-1,$i));return round((float)$values[$i],$precision);
    }

    private static function routeKey(string $path): string
    {
        $path=parse_url($path,PHP_URL_PATH)?:'/';
        if(str_starts_with($path,'/media/'))return'/media/*';
        if(str_starts_with($path,'/admin/')){$parts=array_values(array_filter(explode('/',$path)));return'/'.implode('/',array_slice($parts,0,3));}
        if($path==='/admin')return'/admin';
        if(preg_match('#^/edition/[^/]+/page/\d+#',$path))return'/edition/*/page/*';
        if(preg_match('#^/epaper/[^/]+/\d{4}-\d{2}-\d{2}#',$path))return'/epaper/*/date';
        if(preg_match('#^/epaper/[^/]+#',$path))return'/epaper/*';
        if(str_starts_with($path,'/api/')){$parts=array_values(array_filter(explode('/',$path)));return'/'.implode('/',array_slice($parts,0,4));}
        if(str_starts_with($path,'/account/'))return'/account/*';
        return substr($path,0,120)?:'/';
    }
}
