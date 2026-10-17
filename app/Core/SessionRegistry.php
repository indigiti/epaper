<?php
namespace Digiti\Core;
final class SessionRegistry
{
    private static function hash():string{return hash('sha256',session_id(),true);}
    public static function registerAdmin(string $guid):void{self::register(null,$guid);}
    public static function registerUser(string $guid):void{self::register($guid,null);}
    private static function register(?string $user,?string $admin):void
    {
        Store::exec('INSERT INTO identity_sessions(session_guid,user_guid,admin_guid,session_hash,created_at,last_seen_at,expires_at,user_agent,ip_address) VALUES(?,?,?,?,NOW(6),NOW(6),DATE_ADD(NOW(6),INTERVAL 12 HOUR),?,?)',[Uuid::toBinary(Uuid::v7()),$user?Uuid::toBinary($user):null,$admin?Uuid::toBinary($admin):null,self::hash(),substr($_SERVER['HTTP_USER_AGENT']??'',0,255),$_SERVER['REMOTE_ADDR']??'']);
    }
    public static function activeAdmin(string $guid):bool{return self::active('admin_guid',$guid);}
    public static function activeUser(string $guid):bool{return self::active('user_guid',$guid);}
    private static function active(string $column,string $guid):bool
    {
        $row=Store::one("SELECT session_guid,last_seen_at FROM identity_sessions WHERE {$column}=? AND session_hash=? AND revoked_at IS NULL AND expires_at>NOW() LIMIT 1",[Uuid::toBinary($guid),self::hash()]);if(!$row)return false;if(strtotime($row['last_seen_at'])<time()-60)Store::exec('UPDATE identity_sessions SET last_seen_at=NOW(6) WHERE session_guid=?',[Uuid::toBinary($row['session_guid'])]);return true;
    }
    public static function revokeCurrent():void{if(session_status()!==PHP_SESSION_ACTIVE||session_id()==='')return;try{Store::exec('UPDATE identity_sessions SET revoked_at=NOW(6) WHERE session_hash=? AND revoked_at IS NULL',[self::hash()]);}catch(\Throwable){}}
}
