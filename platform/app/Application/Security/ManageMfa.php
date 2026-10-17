<?php
declare(strict_types=1);
namespace Digiti\Application\Security;

use Digiti\Core\{Audit,Crypto,Mfa,Store,Uuid};

/** Account self-service; caller is already authenticated in the matching context. */
final class ManageMfa
{
    public static function change(string $kind,string $guid,array $input,string $setupSecret):string
    {
        if(!in_array($kind,['platform_admins','platform_users'],true)||!Uuid::isValid($guid))
            throw new \InvalidArgumentException('Invalid security principal');
        $column=$kind==='platform_admins'?'admin_guid':'user_guid';
        $action=(string)($input['action']??'enable');
        if(!in_array($action,['enable','disable'],true))throw new \InvalidArgumentException('Unknown MFA operation');
        Store::tx(static function()use($kind,$column,$guid,$input,$action,$setupSecret):void{
            $row=Store::one("SELECT password_hash,mfa_secret_enc,mfa_enabled FROM $kind WHERE $column=? FOR UPDATE",[Uuid::toBinary($guid)]);
            if(!$row||!password_verify((string)($input['password']??''),(string)$row['password_hash']))
                throw new \DomainException('Current password verification failed');
            if($action==='enable'){
                if(!empty($row['mfa_enabled'])||!Mfa::verify($setupSecret,(string)($input['totp']??'')))
                    throw new \DomainException('Authenticator setup code invalid');
                Store::exec("UPDATE $kind SET mfa_secret_enc=?,mfa_enabled=1,updated_at=NOW(6) WHERE $column=?",[Crypto::encrypt($setupSecret),Uuid::toBinary($guid)]);
            }else{
                if(empty($row['mfa_enabled'])||!Mfa::verify(Crypto::decrypt($row['mfa_secret_enc']),(string)($input['totp']??'')))
                    throw new \DomainException('Authenticator code invalid');
                Store::exec("UPDATE $kind SET mfa_secret_enc=NULL,mfa_enabled=0,updated_at=NOW(6) WHERE $column=?",[Uuid::toBinary($guid)]);
            }
        });
        Audit::log('mfa.'.$action,$kind==='platform_admins'?'platform_admin':'platform_user',$guid);
        return $action;
    }
}
