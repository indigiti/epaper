<?php
declare(strict_types=1);
namespace Digiti\Application\Billing;

use Digiti\Core\{Audit,Uuid};
use Digiti\Domain\Billing\Money;
use Digiti\Infrastructure\Billing\InvoiceRepository;

/** Admin-authorized invoice use case. The caller owns HTTP CSRF/admin checks. */
final class CreateInvoice
{
    public static function execute(string $tenantGuid,string $inrAmount,?InvoiceRepository $repository=null): string
    {
        $tenantGuid=trim($tenantGuid);
        if(!Uuid::isValid($tenantGuid))throw new \InvalidArgumentException('Select an active product tenant.');
        $amount=Money::inrMinor($inrAmount);
        $guid=Uuid::v7();
        $number='DGT-'.gmdate('Ymd').'-'.strtoupper(substr(str_replace('-','',$guid),-8));
        ($repository??new InvoiceRepository())->create($tenantGuid,$amount,$guid,$number);
        Audit::log('invoice.create','invoice',$guid);
        return $number;
    }
}
