<?php
declare(strict_types=1);
namespace Digiti\Contracts;
interface OrganisationReader
{
    /** @return array<string, mixed>|null */
    public function byGuid(string $guidBinary): ?array;
}
