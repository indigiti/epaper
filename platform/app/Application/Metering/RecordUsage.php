<?php
declare(strict_types=1);
namespace Digiti\Application\Metering;

use Digiti\Core\{RedisStore,Uuid};
use Digiti\Domain\Metering\UsagePolicy;
use Digiti\Infrastructure\Metering\UsageRepository;

final class RecordUsage
{
    public static function execute(string $tenant,array $payload,?UsageRepository $repo=null): void
    {
        if(!Uuid::isValid($tenant))throw new \InvalidArgumentException('Invalid tenant.');
        $feature=trim((string)($payload['feature_key']??''));
        if($feature===''||strlen($feature)>128)throw new \InvalidArgumentException('Invalid feature key.');
        $delta=UsagePolicy::delta($payload['delta']??null);
        $period=UsagePolicy::period((string)($payload['period_key']??gmdate('Y-m')));
        ($repo??new UsageRepository())->record($tenant,$feature,$period,$delta);
        RedisStore::del('entitlements:'.$tenant);
    }
}
