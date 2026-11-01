<?php
declare(strict_types=1);
namespace EpaperSaas\Contracts;

/** Tenant-scoped read-only evidence for admin QA. No mutations or synthetic passes. */
interface AcceptanceMetricsRepository
{
    /** @return array<string,array<string,int|bool>|null> */
    public function collect(string $tenantBinary,bool $aiEnabled): array;
}
