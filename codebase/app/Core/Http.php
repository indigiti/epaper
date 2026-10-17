<?php
namespace EpaperSaas\Core;
final class Http
{
    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');header('Referrer-Policy: strict-origin-when-cross-origin');header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self' ".self::platformOrigin()."; worker-src 'self' blob:; manifest-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self' ".self::platformOrigin());
        if(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')header('Strict-Transport-Security: max-age=31536000');
    }
    private static function platformOrigin(): string{try{$u=parse_url((string)Config::get('platform.url','https://platform.digiti.in'));return (($u['scheme']??'https').'://'.($u['host']??'platform.digiti.in'));}catch(\Throwable){return 'https://platform.digiti.in';}}
    public static function requestGuid(): string{static $id;if($id)return $id;$id=Uuid::v7();header('X-Request-Guid: '.$id);return $id;}
    public static function correlationGuid(): string{static $id;if($id)return $id;$h=$_SERVER['HTTP_X_CORRELATION_GUID']??'';$id=Uuid::isValid((string)$h)?strtolower((string)$h):Uuid::v7();header('X-Correlation-Guid: '.$id);return $id;}
    public static function privateNoStore(): void{header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');header('Pragma: no-cache');header('Expires: 0');header('Surrogate-Control: no-store');header('Vary: Cookie',false);}
    public static function publicCache(int $browser=60,int $shared=300,int $stale=600): void{if(Auth::user()){self::privateNoStore();return;}header('Cache-Control: public, max-age='.max(0,$browser).', s-maxage='.max(0,$shared).', stale-while-revalidate='.max(0,$stale));header('Vary: Cookie, Accept-Encoding',false);}
    public static function publicStaticCache(int $browser=300,int $shared=1800,int $stale=3600): void{if(Auth::user()){self::privateNoStore();return;}header('Cache-Control: public, max-age='.max(0,$browser).', s-maxage='.max(0,$shared).', stale-while-revalidate='.max(0,$stale));header('Vary: Accept-Encoding',false);}
    public static function immutable(int $seconds=31536000): void{header('Cache-Control: public, max-age='.max(0,$seconds).', immutable');header('Vary: Accept-Encoding',false);}
    public static function json(array $data,int $status=200): never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
    public static function redirect(string $url): never{header('Location: '.$url);exit;}
    public static function bodyJson(): array{$d=json_decode(file_get_contents('php://input')?:'[]',true);return is_array($d)?$d:[];}
    public static function csrf(): string{Auth::start();if(empty($_SESSION['_csrf']))$_SESSION['_csrf']=bin2hex(random_bytes(24));return $_SESSION['_csrf'];}
    public static function rotateCsrf(): string{Auth::start();$_SESSION['_csrf']=bin2hex(random_bytes(24));return $_SESSION['_csrf'];}
    public static function csrfValid(): bool{Auth::start();$t=$_POST['_csrf']??($_SERVER['HTTP_X_CSRF_TOKEN']??'');return is_string($t)&&$t!==''&&!empty($_SESSION['_csrf'])&&hash_equals((string)$_SESSION['_csrf'],$t);}
    public static function verifyCsrf(): void{if(!self::csrfValid())self::json(['ok'=>false,'code'=>'CSRF_INVALID','request_guid'=>self::requestGuid()],419);}
    public static function host(): string{$h=strtolower(trim((string)($_SERVER['HTTP_HOST']??'')));$h=preg_replace('/:\d+$/','',$h);$h=rtrim($h,'.');if(!preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',$h))throw new \RuntimeException('Invalid Host header');return $h;}
    public static function origin(): string{$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';return $scheme.'://'.self::host();}
}
