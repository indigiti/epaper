<?php
declare(strict_types=1);
namespace Digiti\Application\Metering;

use Digiti\Core\{RedisStore,Uuid};
use Digiti\Domain\Metering\UsagePolicy;
use Digiti\Infrastructure\Metering\UsageRepository;

final class RecordUsage
{
    /** true = recorded now; false = identical event already applied. */
    public static function execute(string $tenant,array $payload,?UsageRepository $repo=null): bool
    {
        if(!Uuid::isValid($tenant))throw new \InvalidArgumentException('Invalid tenant.');
        $feature=trim((string)($payload['feature_key']??''));
        if($feature===''||strlen($feature)>128)throw new \InvalidArgumentException('Invalid feature key.');
        $delta=UsagePolicy::delta($payload['delta']??null);
        $period=UsagePolicy::period((string)($payload['period_key']??gmdate('Y-m')));
        // Omitted ID preserves legacy calls, but cannot deduplicate an HTTP retry.
        $event=(string)($payload['event_guid']??Uuid::v7());
        if(!Uuid::isValid($event))throw new \InvalidArgumentException('Valid usage event GUID required.');
        $recorded=($repo??new UsageRepository())->record($tenant,$feature,$period,$delta,$event);
        if($recorded)RedisStore::del('entitlements:'.$tenant);
        return $recorded;
    }
}
