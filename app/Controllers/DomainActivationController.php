<?php
declare(strict_types=1);
namespace Digiti\Controllers;
use Digiti\Contracts\DomainActivationRepository;
/** Injectable domain-management transition behind existing admin RBAC/CSRF. */
final class DomainActivationController
{
    public function __construct(private readonly DomainActivationRepository $domains) {}
    public function activate(string $guid): string
    {
        $domain=$this->domains->byGuid($guid);
        if(!$domain)throw new \RuntimeException('Domain not found');
        if(!in_array($domain['status'],['ROUTING_VALID','SSL_PROVISIONING'],true))
            throw new \RuntimeException('Domain is not ready for activation');
        $this->domains->activate($guid);
        return $domain['hostname'];
    }
}
