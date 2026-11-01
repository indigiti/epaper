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
                        $return=self::returnPath($path);
                        Http::redirect($return);
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

    /**
     * A failed POST must return to a real GET screen. Do not send operators
     * to a POST-only endpoint (which previously bounced to /admin).
     */
    public static function returnPath(string $path): string
    {
        if(preg_match('#^/admin/onboarding/([0-9a-f-]{36})/(?:verify|owner|confirm|reassign)$#i',$path,$m))
            return '/admin/onboarding?domain='.rawurlencode(strtolower($m[1])).'#domain-review';
        if(preg_match('#^/admin/domains/([0-9a-f-]{36})/mapping$#i',$path,$m))
            return '/admin/domains/'.strtolower($m[1]).'/edit';
        if(preg_match('#^/admin/domains/([0-9a-f-]{36})/verify$#i',$path,$m))
            return '/admin/onboarding?domain='.rawurlencode(strtolower($m[1])).'#domain-review';
        if(preg_match('#^/admin/domains/([0-9a-f-]{36})/restore$#i',$path,$m))
            return '/admin/domains/'.strtolower($m[1]).'/restore';
        if(preg_match('#^/admin/(domains|products|organisations)/([0-9a-f-]{36})/release$#i',$path,$m))
            return '/admin/'.strtolower($m[1]).'/'.strtolower($m[2]).'/release';
        if(preg_match('#^/admin/(?:organisations|users|products|applications|product-tenants|service-accounts|commercial/plans)/([0-9a-f-]{36})/(?:restore|edit)$#i',$path))
            return $path;
        if(preg_match('#^/admin/(?:organisations|users|products|applications|product-tenants|domains|service-accounts|commercial/plans)/[0-9a-f-]{36}/(?:status|remove|rotate)$#i',$path))
            return preg_replace('#/[0-9a-f-]{36}/(?:status|remove|rotate)$#i','',$path);
        if(in_array($path,['/admin/onboarding','/admin/domains','/admin/organisations','/admin/users',
            '/admin/products','/admin/applications','/admin/product-tenants','/admin/service-accounts',
            '/admin/subscriptions','/admin/staging-reset'],true)) return $path;
        return '/admin';
    }

}
