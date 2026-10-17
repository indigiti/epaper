<?php
declare(strict_types=1);
namespace Digiti\Domain\Billing;

/** Exact decimal INR parsing; never use floating point for persisted amounts. */
final class Money
{
    public static function inrMinor(string $raw): int
    {
        $value=trim($raw);
        if(!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D',$value,$m))
            throw new \InvalidArgumentException('Enter a valid INR amount with up to two decimal places.');
        $whole=(int)$m[1];
        $fraction=(int)str_pad($m[2]??'0',2,'0',STR_PAD_RIGHT);
        $amount=$whole*100+$fraction;
        if($amount<=0)throw new \InvalidArgumentException('Invoice amount must be greater than zero.');
        return $amount;
    }
}
