<?php
declare(strict_types=1);
namespace Digiti\Domain\Metering;

/** Bounded decimal quantities, not executable expressions or unbounded floats. */
final class UsagePolicy
{
    public static function delta(mixed $value): string
    {
        if(is_int($value)||is_float($value)){
            if(!is_finite((float)$value)||abs((float)$value)>999999999)
                throw new \InvalidArgumentException('Invalid usage quantity.');
            if(abs((float)$value-round((float)$value,4))>0.00000001)
                throw new \InvalidArgumentException('Usage precision exceeds four decimal places.');
            $value=sprintf('%.4F',(float)$value);
        }
        if(!is_string($value)||!preg_match('/^-?(0|[1-9][0-9]{0,8})(?:\.[0-9]{1,4})?$/D',$value))
            throw new \InvalidArgumentException('Usage delta must be a bounded decimal of up to four places.');
        return $value;
    }

    public static function period(string $period): string
    {
        if(!preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D',$period))
            throw new \InvalidArgumentException('Usage period must be YYYY-MM.');
        return $period;
    }
}
