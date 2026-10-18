<?php
declare(strict_types=1);
namespace Digiti\Domain;
/** Fail-closed product isolation for bounded service accounts. */
final class ServiceProductScope
{
    public static function allows(?string $serviceProductGuid,?string $resourceProductGuid): bool
    {
        if($serviceProductGuid===null || $serviceProductGuid==='')return true;
        if($resourceProductGuid===null || $resourceProductGuid==='')return false;
        return hash_equals(strtolower($serviceProductGuid),strtolower($resourceProductGuid));
    }
}
