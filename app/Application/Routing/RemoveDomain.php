<?php
declare(strict_types=1);
namespace Digiti\Application\Routing;

use Digiti\Core\{Audit,RedisStore,Uuid};
use Digiti\Infrastructure\Routing\DomainLifecycleRepository;

final class RemoveDomain
{
    public static function execute(string $guid,?DomainLifecycleRepository $repository=null): void
    {
        if(!Uuid::isValid($guid))throw new \InvalidArgumentException('Invalid domain.');
        $host=($repository??new DomainLifecycleRepository())->remove($guid);
        RedisStore::del('domain:'.$host);
        Audit::log('domain.remove','domain',$guid);
    }
}
