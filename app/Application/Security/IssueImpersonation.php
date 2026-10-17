<?php
declare(strict_types=1);
namespace Digiti\Application\Security;

use Digiti\Core\{Audit,Store,Uuid};

/** Mint time-bounded, one-time support grants for an ACTIVE organisation membership. */
final class IssueImpersonation
{
    public static function issue(string $adminGuid,array $input):string
    {
        if(!Uuid::isValid($adminGuid)||!Uuid::isValid((string)($input['membership_guid']??'')))
            throw new \InvalidArgumentException('Invalid Admin or membership');
        $reason=trim((string)($input['reason']??''));
        if(mb_strlen($reason)<8||mb_strlen($reason)>500)
            throw new \InvalidArgumentException('Support reason must be 8 to 500 characters');
        $guid=Uuid::v7();$token='imp_'.rtrim(strtr(base64_encode(random_bytes(36)),'+/','-_'),'=');
        $member=Store::tx(static function()use($adminGuid,$input,$reason,$token,$guid):array {
            $member=Store::one('SELECT m.user_guid,m.organisation_guid FROM memberships m JOIN platform_users u ON u.user_guid=m.user_guid AND u.status="ACTIVE" JOIN organisations o ON o.organisation_guid=m.organisation_guid AND o.status="ACTIVE" WHERE m.membership_guid=? AND m.status="ACTIVE" FOR UPDATE',[Uuid::toBinary((string)$input['membership_guid'])]);
            if(!$member)throw new \DomainException('Active support membership not found');
            Store::exec('INSERT INTO impersonation_grants(impersonation_guid,admin_guid,organisation_guid,user_guid,token_hash,reason,created_at,expires_at) VALUES(?,?,?,?,?,?,NOW(6),DATE_ADD(NOW(6),INTERVAL 10 MINUTE))',[
                Uuid::toBinary($guid),Uuid::toBinary($adminGuid),Uuid::toBinary($member['organisation_guid']),
                Uuid::toBinary($member['user_guid']),hash('sha256',$token,true),$reason]);
            return $member;
        });
        Audit::log('impersonation.create','impersonation_grant',$guid,'SUCCESS',[
            'organisation_guid'=>$member['organisation_guid'],'user_guid'=>$member['user_guid'],'reason'=>$reason]);
        return $token;
    }
}
