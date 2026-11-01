<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\{Auth,Config,DomainTools,Store,Uuid};

/**
 * Domain registry and Publisher Onboarding must agree on whether a hostname
 * is a customer alias or the ePaper application's own canonical base URL.
 *
 * Registered active application/product GUIDs and Base URL determine ownership,
 * regardless of mutable/legacy product or application keys.
 *
 * This class only classifies/authorizes an INSERT. It does not bypass the
 * unique hostname constraint, reassign publishers or activate DNS routing.
 */
final class CanonicalDomainRegistration
{
    public static function classify(string $hostname,array $selectedApplication): string
    {
        $known=Store::all('SELECT a.application_guid,a.product_guid,a.base_url,p.product_key
            FROM applications a JOIN products p ON p.product_guid=a.product_guid
            WHERE a.status="ACTIVE" AND p.status="ACTIVE"');
        $admin=Auth::requireAdmin();
        $role=Store::value('SELECT role_key FROM platform_admins WHERE admin_guid=? AND status="ACTIVE"',[
            Uuid::toBinary((string)$admin['admin_guid'])
        ]);
        return self::classifyKnown($hostname,$selectedApplication,$known,$role==='SUPER_ADMIN');
    }

    /**
     * Pure classifier for deterministic regression tests.
     * Matching an application base URL is legitimate for the *selected*
     * active application and product; unrelated apps and infrastructure hosts stay blocked.
     */
    public static function classifyKnown(string $hostname,array $selectedApplication,array $registeredApps,bool $superAdmin): string
    {
        $host=DomainTools::publicHostname($hostname);
        $platformHost=strtolower((string)(parse_url((string)Config::get('app.url',''),PHP_URL_HOST)?:''));
        $target=DomainTools::normalize((string)Config::get('app.domains_target','domains.digiti.in'));
        if(($platformHost!=='' && $host===$platformHost) || $host===$target
            || in_array($host,['platform.digiti.in','domains.digiti.in'],true)) {
            throw new \InvalidArgumentException('This is a Platform infrastructure hostname. It cannot be registered as a publisher or ePaper application domain.');
        }

        $matches=[];
        foreach($registeredApps as $app) {
            $canonical=strtolower((string)(parse_url((string)($app['base_url']??''),PHP_URL_HOST)?:''));
            if($canonical!=='' && $canonical===$host)$matches[]=$app;
        }
        if(!$matches)return 'CUSTOMER_CUSTOM';
        if(count($matches)!==1)
            throw new \InvalidArgumentException('Several applications use this same base URL. Correct the application registry before assigning its hostname.');

        $registered=$matches[0];
        if(($registered['application_guid']??'')!==($selectedApplication['application_guid']??'')
            ||($registered['product_guid']??'')!==($selectedApplication['product_guid']??'')) {
            throw new \InvalidArgumentException('This hostname belongs to a different application. Choose the application whose Base URL matches this website.');
        }
        if(!$superAdmin)
            throw new \InvalidArgumentException('Only a Platform SUPER_ADMIN may register a canonical application hostname.');
        return 'SAAS_DEFAULT';
    }

    /**
     * The one registration writer for Domain Registry and Publisher Onboarding.
     * Must be called inside the caller's transaction, so new-tenant creation
     * and hostname registration commit/rollback together.
     */
    public static function insertDomain(string $guid,string $host,string $token,array $tenant,array $app,string $domainType): void
    {
        if(!in_array($domainType,['SAAS_DEFAULT','CUSTOMER_CUSTOM'],true))
            throw new \InvalidArgumentException('Invalid website registration type.');
        if(!isset($tenant['product_tenant_guid'],$tenant['organisation_guid'],$tenant['product_guid'],
                  $app['application_guid'],$app['product_guid'])
           ||$tenant['product_guid']!==$app['product_guid'])
            throw new \InvalidArgumentException('Publisher tenant and application must belong to the same product.');

        Store::exec('INSERT INTO domains(domain_guid,organisation_guid,product_guid,application_guid,
            product_tenant_guid,hostname,normalized_hostname,domain_type,status,is_primary,
            verification_token,created_at,updated_at)
            VALUES(?,?,?,?,?,?,?,?,"AWAITING_DNS",0,?,NOW(6),NOW(6))',[
            Uuid::toBinary($guid),Uuid::toBinary((string)$tenant['organisation_guid']),
            Uuid::toBinary((string)$tenant['product_guid']),
            Uuid::toBinary((string)$app['application_guid']),
            Uuid::toBinary((string)$tenant['product_tenant_guid']),$host,$host,$domainType,$token
        ]);
        Store::exec('INSERT INTO application_redirect_uris(redirect_guid,application_guid,
            redirect_uri,status,created_at) VALUES(?,?,?,"ACTIVE",NOW(6))
            ON DUPLICATE KEY UPDATE status="ACTIVE"',[
            Uuid::toBinary(Uuid::v7()),Uuid::toBinary((string)$app['application_guid']),
            'https://'.$host.'/auth/callback'
        ]);
    }

}
