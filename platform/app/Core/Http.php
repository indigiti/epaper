<?php
namespace Digiti\Core;

final class Http
{
    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        if (!headers_sent() && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    public static function json(array $data,int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit; }
    public static function redirect(string $url): never { header('Location: '.$url); exit; }
    public static function bodyJson(): array { $d=json_decode(file_get_contents('php://input')?:'[]',true); return is_array($d)?$d:[]; }
    public static function requestGuid(): string
    {
        static $id; if ($id) return $id; $id=Uuid::v7(); header('X-Request-Guid: '.$id); return $id;
    }
    public static function csrf(): string
    {
        Auth::start(); // Always use the named, secure Platform session.
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(24));
        return $_SESSION['_csrf'];
    }
    public static function verifyCsrf(): void
    {
        Auth::start(); // Do not silently open a default PHPSESSID session.
        $raw=$_POST['_csrf']??($_SERVER['HTTP_X_CSRF_TOKEN']??'');
        $token=is_string($raw)?$raw:'';
        $expected=$_SESSION['_csrf']??null;
        if ($token!=='' && is_string($expected) && $expected!=='' && hash_equals($expected,$token)) return;
        // Log only the rejection category and request GUID, never session IDs, tokens or passwords.
        $path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
        if ($path==='/login') {
            $reason=$token===''?'missing_form_token':(!is_string($expected)||$expected===''?'missing_session_token':'token_mismatch');
            error_log('Digiti Platform login CSRF rejected: reason='.$reason.' request_guid='.self::requestGuid());
        }
        self::json(['ok'=>false,'code'=>'CSRF_INVALID','message'=>'Invalid CSRF token','request_guid'=>self::requestGuid()],419);
    }
}
