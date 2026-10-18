<?php
declare(strict_types=1);
namespace Digiti\Repositories;
use Digiti\Contracts\OrganisationReader;
use Digiti\Core\Store;
final class SqlOrganisationReader implements OrganisationReader
{
    public function byGuid(string $guidBinary): ?array
    {
        return Store::one('SELECT organisation_guid,organisation_code,legal_name,display_name,status FROM organisations WHERE organisation_guid=?',[$guidBinary]);
    }
}
