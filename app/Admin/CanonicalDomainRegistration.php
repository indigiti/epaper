<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\{Auth,Config,DomainTools,Store,Uuid};

/**
 * Domain registry and Publisher Onboarding must agree on whether a hostname
 * is a customer alias or the ePaper application's own canonical base URL.
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
     * active EPAPER app; unrelated apps and infrastructure hosts stay blocked.
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
        if(($registered['product_key']??'')!=='EPAPER')
            throw new \InvalidArgumentException('Only the registered EPAPER application can use canonical ePaper hostname registration.');
        if(!$superAdmin)
            throw new \InvalidArgumentException('Only a Platform SUPER_ADMIN may register the main ePaper application hostname.');
        return 'SAAS_DEFAULT';
    }
}
