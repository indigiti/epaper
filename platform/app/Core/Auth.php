<?php
namespace Digiti\Core;

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name('digiti_platform');
        session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax','path'=>'/']);
        // A missing/unwritable PHP session store must fail closed, not masquerade as CSRF failure.
        if (!@session_start() || session_status() !== PHP_SESSION_ACTIVE) {
            error_log('Digiti Platform: PHP session initialization failed; check PHP-FPM session.save_path and permissions');
            throw new \RuntimeException('Session unavailable. Check PHP session storage.');
        }
    }
    public static function admin(): ?array
    {
        self::start(); $guid=$_SESSION['platform_admin_guid']??null; if(!$guid)return null;
        if(!SessionRegistry::activeAdmin($guid)){unset($_SESSION['platform_admin_guid']);return null;} return Store::one('SELECT admin_guid,email,display_name,status,mfa_enabled FROM platform_admins WHERE admin_guid=? AND status="ACTIVE"',[Uuid::toBinary($guid)]);
    }
    public static function requireAdmin(): array { $a=self::admin(); if(!$a) Http::redirect('/login'); return $a; }
    public static function login(string $email,string $password,string $totp=''): bool
    {
        $row=Store::one('SELECT admin_guid,email,display_name,password_hash,status,mfa_enabled,mfa_secret_enc FROM platform_admins WHERE email=? LIMIT 1',[strtolower(trim($email))]);
        if(!$row||$row['status']!=='ACTIVE'||!password_verify($password,$row['password_hash'])) return false; if(!empty($row['mfa_enabled'])){try{$secret=Crypto::decrypt($row['mfa_secret_enc']);}catch(\Throwable){return false;}if(!Mfa::verify($secret,$totp))return false;} self::start(); session_regenerate_id(true); $_SESSION['platform_admin_guid']=$row['admin_guid']; SessionRegistry::registerAdmin($row['admin_guid']); return true;
    }
    public static function logout(): void { self::start(); SessionRegistry::revokeCurrent(); $_SESSION=[]; if(ini_get('session.use_cookies')){ $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']); } session_destroy(); }
}
