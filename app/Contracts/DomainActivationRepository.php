<?php
declare(strict_types=1);
namespace Digiti\Contracts;
interface DomainActivationRepository
{
    /** @return array<string,mixed>|null */
    public function byGuid(string $domainGuid): ?array;
    public function activate(string $domainGuid): void;
}
