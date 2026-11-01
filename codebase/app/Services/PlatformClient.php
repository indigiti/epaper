<?php
namespace EpaperSaas\Services;
use EpaperSaas\Core\{Config,Http,RedisStore,RequestContext};
final class PlatformClient
{
    private static ?self $self=null;
    public static function instance(): self{return self::$self??=new self();}
    private function call(string $method,string $path,?array $body=null,array $query=[]): array
    {
        $base=rtrim((string)Config::get('platform.url','https://platform.digiti.in'),'/');$url=$base.$path.($query?'?'.http_build_query($query):'');$headers=['Accept: application/json','X-Digiti-Service-Token: '.(string)Config::get('platform.service_token'),'X-Request-Guid: '.Http::requestGuid(),'X-Correlation-Guid: '.Http::correlationGuid()];$payload=$body===null?null:json_encode($body,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);if($payload!==null)$headers[]='Content-Type: application/json';
        if(function_exists('curl_init')){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>6,CURLOPT_CONNECTTIMEOUT=>2]);if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$payload);$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);if($raw===false)throw new \RuntimeException('Platform unavailable: '.$err);}
        else{$ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$payload??'','timeout'=>6,'ignore_errors'=>true]]);$raw=@file_get_contents($url,false,$ctx);$code=0;if(!empty($http_response_header[0])&&preg_match('/\s(\d{3})\s/',$http_response_header[0],$m))$code=(int)$m[1];if($raw===false)throw new \RuntimeException('Platform unavailable');}
        $data=json_decode((string)$raw,true);if(!is_array($data))throw new \RuntimeException('Invalid Platform response');if($code>=400){$reason=trim((string)($data['code']??''));if($reason==='')$reason=trim((string)($data['message']??''));throw new \RuntimeException($reason!==''?$reason:'PLATFORM_REQUEST_FAILED',$code);}return$data;
    }
    /** Stable, non-sensitive error codes for public readiness diagnostics. */
    public static function diagnosticCode(\Throwable $error): string
    {
        return match(strtoupper(trim($error->getMessage()))){
            'SERVICE_AUTH_INVALID','INVALID SERVICE TOKEN'=>'SERVICE_AUTH_INVALID',
            'SERVICE_AUTH_REQUIRED','SERVICE TOKEN REQUIRED'=>'SERVICE_AUTH_REQUIRED',
            'SERVICE_SCOPE_DENIED','SERVICE SCOPE DENIED'=>'SERVICE_SCOPE_DENIED',
            'SERVICE_PRODUCT_INACTIVE'=>'SERVICE_PRODUCT_INACTIVE',
            'SERVICE_PRODUCT_MISMATCH'=>'SERVICE_PRODUCT_MISMATCH',
            'DOMAIN_NOT_ACTIVE'=>'DOMAIN_NOT_ACTIVE',
            'DOMAIN_SETUP_PENDING'=>'DOMAIN_SETUP_PENDING',
            default=>'PLATFORM_RESOLVE_FAILED',
        };
    }
    public function health(): array{try{return $this->call('GET','/health/live');}catch(\Throwable $e){return['ok'=>false,'status'=>'UNAVAILABLE','message'=>$e->getMessage()];}}
    public function resolveDomain(string $host): array{return $this->call('GET','/internal/v1/domains/resolve',null,['hostname'=>$host]);}
    public function tenant(string $guid): array{return $this->call('GET','/internal/v1/tenants/'.$guid);}
    public function access(string $guid): array{return $this->call('GET','/internal/v1/tenants/'.$guid.'/access');}
    public function entitlements(string $guid): array{return $this->call('GET','/internal/v1/tenants/'.$guid.'/entitlements');}
    public function verifyMembership(string $user,string $org): array{return $this->call('POST','/internal/v1/memberships/verify',['user_guid'=>$user,'organisation_guid'=>$org]);}
    public function exchangeAuth(string $code,string $verifier): array{$d=$this->call('POST','/internal/v1/auth/token',['code'=>$code,'code_verifier'=>$verifier,'client_id'=>Config::get('platform.oauth_client_id')]);return$d['identity']??[];}
    public function directAuth(string $tenant,string $email,string $password,string $totp=''): array{$d=$this->call('POST','/internal/v1/auth/direct',['client_id'=>Config::get('platform.oauth_client_id'),'product_tenant_guid'=>$tenant,'email'=>$email,'password'=>$password,'totp'=>$totp]);return$d['identity']??[];}
    public function usage(string $tenant,string $feature,float $delta,string $period=''): void{try{$this->call('POST','/internal/v1/usage',['product_tenant_guid'=>$tenant,'feature_key'=>$feature,'delta'=>$delta,'period_key'=>$period?:gmdate('Y-m')]);}catch(\Throwable){}}
    public function heartbeat(array $metrics=[]): bool{try{$this->call('POST','/internal/v1/heartbeats',['application_key'=>Config::get('platform.application_key','city_epaper'),'environment'=>Config::get('app.environment','production'),'version'=>trim((string)@file_get_contents(dirname(__DIR__,2).'/VERSION')),'status'=>'ONLINE','metrics'=>$metrics]);return true;}catch(\Throwable){return false;}}
    public function audit(array $d): void{try{$this->call('POST','/internal/v1/audit',$d);}catch(\Throwable){}}
    public function error(array $d): void{try{$this->call('POST','/internal/v1/errors',$d);}catch(\Throwable){}}
}
