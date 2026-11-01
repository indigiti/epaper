<?php
namespace Digiti\Core;

final class ErrorCenter
{
    public static function register(): void
    {
        set_exception_handler(function(\Throwable $e){
            // Show input mistakes as friendly, actionable messages instead of a generic 500.
            // Only administrator form submissions are eligible. Do not expose SQL/runtime errors.
            $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
            if($e instanceof \InvalidArgumentException
                && ($_SERVER['REQUEST_METHOD']??'GET')==='POST'
                && str_starts_with($path,'/admin/')) {
                try {
                    if(Auth::admin()) {
                        View::flash(substr($e->getMessage(),0,250));
                        if(preg_match('#^/admin/onboarding/([0-9a-f-]{36})/[^/]+$#',$path,$matches))
                            Http::redirect('/admin/onboarding?domain='.rawurlencode($matches[1]));
                        Http::redirect($path);
                    }
                } catch(\Throwable) {}
            }

            $error=Uuid::v7();
            try { Store::exec('INSERT INTO error_events(error_guid,occurred_at,request_guid,level,exception_class,message,file,line,stack_trace,fingerprint) VALUES(?,NOW(6),?,?,?,?,?,?,?,?,?)',[
                Uuid::toBinary($error),Uuid::toBinary(Http::requestGuid()),'ERROR',get_class($e),substr($e->getMessage(),0,1000),$e->getFile(),$e->getLine(),substr($e->getTraceAsString(),0,16000),hash('sha256',get_class($e).'|'.$e->getMessage().'|'.$e->getFile().'|'.$e->getLine())
            ]);} catch(\Throwable) {}
            if (str_starts_with($_SERVER['REQUEST_URI']??'','/internal/')||str_starts_with($_SERVER['REQUEST_URI']??'','/health/')) Http::json(['ok'=>false,'code'=>'INTERNAL_ERROR','message'=>'Unexpected server error','request_guid'=>Http::requestGuid(),'error_guid'=>$error],500);
            http_response_code(500); echo '<h1>Request needs attention</h1><p>Reference: '.htmlspecialchars($error).'</p>'; exit;
        });
    }
}
