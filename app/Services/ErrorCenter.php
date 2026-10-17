<?php
namespace EpaperSaas\Services;
use EpaperSaas\Core\{Config,Http,RequestContext,Store,Uuid};
final class ErrorCenter
{
    public static function register(): void
    {
        set_exception_handler(function(\Throwable$e){$guid=self::record($e);if(!headers_sent()){http_response_code(500);header('Content-Type: text/html; charset=utf-8');}echo '<!doctype html><meta charset="utf-8"><title>Request needs attention</title><style>body{font-family:system-ui;background:#f5f5f7;color:#1d1d1f;display:grid;place-items:center;min-height:100vh}.c{max-width:520px;padding:32px;background:white;border-radius:22px;box-shadow:0 20px 50px #0001}.id{font-family:ui-monospace;font-size:12px;color:#6e6e73}</style><div class="c"><h1>Request needs attention</h1><p>We could not complete this request. Please try again.</p><p class="id">Error ID: '.htmlspecialchars($guid).'<br>Request: '.htmlspecialchars(Http::requestGuid()).'</p></div>';exit;});
    }
    public static function record(\Throwable$e,array $meta=[]): string
    {
        $g=Uuid::v7();$ctx=RequestContext::all();try{Store::exec('INSERT INTO ep_error_events(error_guid,tenant_guid,occurred_at,request_guid,correlation_guid,user_guid,level,exception_class,message,file,line,fingerprint,metadata_json) VALUES(?,?,NOW(6),?,?,?,?,?,?,?,?,?,?)',[Uuid::toBinary($g),!empty($ctx['tenant_guid'])?Uuid::toBinary($ctx['tenant_guid']):null,Uuid::toBinary(Http::requestGuid()),Uuid::toBinary(Http::correlationGuid()),!empty($ctx['user_guid'])?Uuid::toBinary($ctx['user_guid']):null,'ERROR',get_class($e),substr($e->getMessage(),0,1000),$e->getFile(),$e->getLine(),hash('sha256',get_class($e).'|'.$e->getFile().'|'.$e->getLine()),json_encode($meta)]);}catch(\Throwable){}
        try{PlatformClient::instance()->error(['error_guid'=>$g,'tenant_guid'=>$ctx['tenant_guid']??null,'domain_guid'=>$ctx['domain_guid']??null,'user_guid'=>$ctx['user_guid']??null,'request_guid'=>Http::requestGuid(),'level'=>'ERROR','exception_class'=>get_class($e),'message'=>$e->getMessage(),'file'=>Config::get('app.environment')==='production'?null:$e->getFile(),'line'=>Config::get('app.environment')==='production'?null:$e->getLine(),'fingerprint'=>hash('sha256',get_class($e).'|'.$e->getFile().'|'.$e->getLine()),'metadata'=>$meta]);}catch(\Throwable){}return$g;
    }
}
