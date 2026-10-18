<?php
declare(strict_types=1);
namespace EpaperSaas\Contracts;
interface EditorialActionRepository
{
    public function editions(string $tenantBinary,string $status,int $limit): array;
    public function failedJobs(string $tenantBinary,int $limit): array;
    public function pendingAi(string $tenantBinary,int $limit): array;
}
