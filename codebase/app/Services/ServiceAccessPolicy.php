<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

final class ServiceAccessPolicy
{
    public const UNAVAILABLE='UNAVAILABLE';

    public static function status(array $response): string
    {
        $raw=$response['service_access_state']??null;
        if(!is_string($raw))return self::UNAVAILABLE;
        $state=strtoupper(trim($raw));
        if(!preg_match('/^[A-Z][A-Z0-9_]{1,47}$/D',$state))return self::UNAVAILABLE;
        return $state;
    }

    public static function permitted(string $state): bool
    {
        return in_array($state,['FULL','RESTRICTED'],true);
    }
}
