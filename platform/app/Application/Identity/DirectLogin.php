<?php
declare(strict_types=1);
namespace Digiti\Application\Identity;

use Digiti\Core\{Audit,OAuth,RateLimiter,Uuid};
use Digiti\Domain\Identity\TenantBoundaryPolicy;
use Digiti\Infrastructure\Identity\IdentityRepository;

/** Service-backed publisher sign-in use case; intentionally independent of HTTP. */
final class DirectLogin
{
    public static function authenticate(array $service,array $payload,?IdentityRepository $repo=null): array
    {
        $repo??=new IdentityRepository();
        $clientId=trim((string)($payload['client_id']??''));
        $tenantGuid=strtolower(trim((string)($payload['product_tenant_guid']??'')));
        $email=strtolower(trim((string)($payload['email']??'')));
        $password=(string)($payload['password']??'');
        $totp=trim((string)($payload['totp']??''));
        if($clientId===''||$tenantGuid===''||$email===''||$password===''||!Uuid::isValid($tenantGuid))
            throw new DirectAuthFailure('AUTH_REQUEST_INVALID',400,'Invalid sign-in request');
        $app=$repo->activeApplication($clientId);
        if(!$app)throw new DirectAuthFailure('AUTH_CLIENT_INVALID',401,'Application authentication is not configured');
        $tenant=$repo->tenant($tenantGuid);
        if(!$tenant)throw new DirectAuthFailure('TENANT_INACTIVE',403,'Publisher account is not active');
        $reason=TenantBoundaryPolicy::denyReason($app,$tenant,$service);
        if($reason!==null) {
            $errors=[
                'AUTH_CLIENT_INVALID'=>[401,'Application authentication is not configured'],
                'TENANT_INACTIVE'=>[403,'Publisher account is not active'],
                'AUTH_PRODUCT_MISMATCH'=>[403,'Application is not authorized for this publisher'],
                'SERVICE_PRODUCT_MISMATCH'=>[403,'Service account is not authorized for this product']
            ];
            [$status,$message]=$errors[$reason];
            throw new DirectAuthFailure($reason,$status,$message);
        }
        $bucket='direct-auth:'.$tenantGuid.':'.hash('sha256',$email);
        if(!RateLimiter::allow($bucket,10,300)) {
            Audit::log('identity.direct_login_rate_limited','product_tenant',$tenantGuid,'DENIED',['application_key'=>$app['application_key']]);
            throw new DirectAuthFailure('AUTH_RATE_LIMITED',429,'Too many sign-in attempts. Try again later.');
        }
        $user=OAuth::verifyCredentials($email,$password,$totp);
        if(!$user) {
            Audit::log('identity.direct_login_failed','product_tenant',$tenantGuid,'DENIED',['application_key'=>$app['application_key'],'email_hash'=>hash('sha256',$email)]);
            throw new DirectAuthFailure('AUTH_INVALID',401,'Invalid email, password or authenticator code');
        }
        $memberships=$repo->activeMemberships($user['user_guid'],$tenant['organisation_guid']);
        if(!$memberships) {
            Audit::log('identity.direct_login_membership_denied','product_tenant',$tenantGuid,'DENIED',['application_key'=>$app['application_key'],'user_guid'=>$user['user_guid']]);
            throw new DirectAuthFailure('MEMBERSHIP_NOT_ACTIVE',403,'No active publisher membership for this organisation');
        }
        $identity=[
            'user'=>$user,'memberships'=>$memberships,'authenticated_at'=>gmdate('c'),
            'assurance'=>!empty($user['mfa_used'])?'PASSWORD_MFA':'PASSWORD',
            'product_tenant_guid'=>$tenantGuid,'application_guid'=>$app['application_guid']
        ];
        unset($identity['user']['mfa_used']);
        Audit::log('identity.direct_login','product_tenant',$tenantGuid,'SUCCESS',['application_key'=>$app['application_key'],'user_guid'=>$user['user_guid']]);
        return $identity;
    }
}
