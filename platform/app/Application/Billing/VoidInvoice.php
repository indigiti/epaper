<?php
declare(strict_types=1);
namespace Digiti\Application\Billing;

use Digiti\Core\{Audit,Uuid};
use Digiti\Infrastructure\Billing\InvoiceRepository;

/** Authorised admin use case; repository guarantees legal transition atomically. */
final class VoidInvoice
{
    public static function execute(string $invoiceGuid,?InvoiceRepository $repository=null): void
    {
        if(!Uuid::isValid($invoiceGuid))throw new \InvalidArgumentException('Invalid invoice reference.');
        if(!($repository??new InvoiceRepository())->voidOpenOrDraft($invoiceGuid))
            throw new \RuntimeException('Invoice is not DRAFT/OPEN or its status has changed. It cannot be voided.');
        Audit::log('invoice.void','invoice',$invoiceGuid);
    }
}
