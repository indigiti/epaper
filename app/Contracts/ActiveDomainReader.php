<?php
declare(strict_types=1);
namespace Digiti\Contracts;
interface ActiveDomainReader
{
    /** @return array<string,mixed>|null */
    public function byHostname(string $normalizedHostname): ?array;
}
