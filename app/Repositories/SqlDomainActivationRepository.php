<?php
declare(strict_types=1);
namespace Digiti\Repositories;
use Digiti\Contracts\DomainActivationRepository;
use Digiti\Core\{Store,Uuid};
/** Uses existing SQL; no domain schema or lifecycle change. */
final class SqlDomainActivationRepository implements DomainActivationRepository
{
    public function byGuid(string $domainGuid): ?array
    {
        return Store::one('SELECT domain_guid,hostname,status FROM domains WHERE domain_guid=?',
                          [Uuid::toBinary($domainGuid)]);
    }
    public function activate(string $domainGuid): void
    {
        Store::exec('UPDATE domains SET status="ACTIVE",ssl_ready_at=COALESCE(ssl_ready_at,NOW(6)),activated_at=COALESCE(activated_at,NOW(6)),updated_at=NOW(6) WHERE domain_guid=?',
                    [Uuid::toBinary($domainGuid)]);
    }
}
