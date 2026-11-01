<?php
namespace Digiti\Core;

final class OAuth
{
    public static function user(): ?array
    {
        Auth::start(); $guid=$_SESSION['digiti_user_guid']??null;if(!$guid)return null;if(!SessionRegistry::activeUser($guid)){unset($_SESSION['digiti_user_guid']);return null;} return Store::one('SELECT user_guid,email,display_name,status FROM platform_users WHERE user_guid=? AND status="ACTIVE"',[Uuid::toBinary($guid)]);
    }
    public static function verifyCredentials(string $email,string $password,string $totp=''): ?array
    {
        $u=Store::one('SELECT user_guid,email,display_name,password_hash,status,mfa_enabled,mfa_secret_enc FROM platform_users WHERE email=? LIMIT 1',[strtolower(trim($email))]);
        if(!$u||$u['status']!=='ACTIVE'||!password_verify($password,(string)$u['password_hash']))return null;
        $mfaUsed=false;
        if(!empty($u['mfa_enabled'])){try{$secret=Crypto::decrypt($u['mfa_secret_enc']);}catch(\Throwable){return null;}if(!Mfa::verify($secret,$totp))return null;$mfaUsed=true;}
        Store::exec('UPDATE platform_users SET last_login_at=NOW(6),updated_at=NOW(6) WHERE user_guid=?',[Uuid::toBinary($u['user_guid'])]);
        return ['user_guid'=>$u['user_guid'],'email'=>$u['email'],'display_name'=>$u['display_name'],'status'=>$u['status'],'mfa_used'=>$mfaUsed];
    }
    public static function login(string $email,string $password,string $totp=''): bool
    {
        $u=self::verifyCredentials($email,$password,$totp);if(!$u)return false;Auth::start();session_regenerate_id(true);$_SESSION['digiti_user_guid']=$u['user_guid'];SessionRegistry::registerUser($u['user_guid']);return true;
    }
    public static function issueCode(array $user,array $app,string $redirect,string $challenge,?string $state): string
    {
        if(!preg_match('/^[A-Za-z0-9_-]{43}$/',$challenge))throw new \InvalidArgumentException('Valid S256 PKCE code_challenge required');
        if(!self::redirectAllowed($app['application_guid'],$redirect,$app['callback_url']??null))throw new \InvalidArgumentException('redirect_uri mismatch');
        if(!self::membershipsForApplication($user['user_guid'],$app['application_guid']))
            throw new \RuntimeException('No active publisher membership for this application.');
        $code=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
        Store::exec('INSERT INTO auth_codes(auth_code_guid,code_hash,user_guid,application_guid,redirect_uri,code_challenge,state,expires_at,created_at) VALUES(?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 2 MINUTE),NOW(6))',[
            Uuid::toBinary(Uuid::v7()),hash('sha256',$code,true),Uuid::toBinary($user['user_guid']),Uuid::toBinary($app['application_guid']),$redirect,$challenge,$state
        ]);return $code;
    }
    public static function redirectAllowed(string $applicationGuid,string $redirect,?string $legacy=null): bool
    {
        if($legacy&&hash_equals($legacy,$redirect))return true;
        $row=Store::one('SELECT redirect_guid FROM application_redirect_uris WHERE application_guid=? AND redirect_uri=? AND status="ACTIVE" LIMIT 1',[Uuid::toBinary($applicationGuid),$redirect]);return(bool)$row;
    }
    public static function exchange(string $code,string $verifier,string $clientId): array
    {
        $row=Store::one('SELECT ac.auth_code_guid,ac.user_guid,ac.application_guid,ac.redirect_uri,ac.code_challenge,ac.expires_at,ac.used_at,a.oauth_client_id FROM auth_codes ac JOIN applications a ON a.application_guid=ac.application_guid JOIN products p ON p.product_guid=a.product_guid WHERE ac.code_hash=? AND a.oauth_client_id=? AND a.status="ACTIVE" AND p.status="ACTIVE" LIMIT 1',[hash('sha256',$code,true),$clientId]);
        if(!$row||$row['used_at']||strtotime($row['expires_at'])<time())throw new \RuntimeException('Invalid or expired authorization code');
        $calc=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');if(!hash_equals($row['code_challenge'],$calc))throw new \RuntimeException('PKCE verification failed');
        $user=Store::one('SELECT user_guid,email,display_name,status FROM platform_users WHERE user_guid=? AND status="ACTIVE"',[Uuid::toBinary($row['user_guid'])]);
        if(!$user)throw new \RuntimeException('Account is no longer active');
        $memberships=self::membershipsForApplication($user['user_guid'],$row['application_guid']);
        if(!$memberships)throw new \RuntimeException('Publisher membership is no longer active');
        $claimed=Store::exec('UPDATE auth_codes SET used_at=NOW(6) WHERE auth_code_guid=? AND used_at IS NULL AND expires_at>NOW(6)',[Uuid::toBinary($row['auth_code_guid'])]);
        if($claimed!==1)throw new \RuntimeException('Authorization code was already used or expired');
        return ['user'=>$user,'memberships'=>$memberships];
    }

    private static function membershipsForApplication(string $userGuid,string $applicationGuid): array
    {
        return Store::all('SELECT m.membership_guid,m.organisation_guid,m.role_key,m.status
            FROM memberships m
            JOIN organisations o ON o.organisation_guid=m.organisation_guid AND o.status="ACTIVE"
            JOIN product_tenants pt ON pt.organisation_guid=m.organisation_guid AND pt.status="ACTIVE"
            JOIN applications a ON a.product_guid=pt.product_guid AND a.status="ACTIVE"
            JOIN products p ON p.product_guid=pt.product_guid AND p.status="ACTIVE"
            WHERE m.user_guid=? AND m.status="ACTIVE" AND a.application_guid=?',[
            Uuid::toBinary($userGuid),Uuid::toBinary($applicationGuid)
        ]);
    }
}
