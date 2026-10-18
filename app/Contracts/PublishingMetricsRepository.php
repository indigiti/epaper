<?php
declare(strict_types=1);
namespace EpaperSaas\Contracts;

/** Read-side port for the publishing health snapshot, scoped to one tenant. */
interface PublishingMetricsRepository
{
    /** @return array<string, mixed> */
    public function summary(string $tenantBinary): array;
}
