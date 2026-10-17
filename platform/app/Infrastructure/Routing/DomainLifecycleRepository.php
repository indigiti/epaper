<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Routing;

use Digiti\Core\{Store,Uuid};

final class DomainLifecycleRepository
{
    /** A removed domain cannot become active due to a stale editor submission. */
    public function remove(string $guid): string
    {
        return Store::tx(static function()use($guid):string {
            $row=Store::one('SELECT hostname,status FROM domains WHERE domain_guid=? FOR UPDATE',[Uuid::toBinary($guid)]);
            if(!$row)throw new \DomainException('Domain not found.');
            Store::exec('UPDATE domains SET status="REMOVED",is_primary=0,updated_at=NOW(6) WHERE domain_guid=?',[Uuid::toBinary($guid)]);
            return (string)$row['hostname'];
        });
    }
}
