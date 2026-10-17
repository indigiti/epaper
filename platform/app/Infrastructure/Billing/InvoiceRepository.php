<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Billing;

use Digiti\Core\{Store,Uuid};

/** Persist invoices and their lines together; refuse inactive/missing tenants. */
final class InvoiceRepository
{
    /** An invoice must never transition from PAID to VOID, even under concurrency. */
    public function voidOpenOrDraft(string $invoiceGuid): bool
    {
        return Store::exec('UPDATE invoices SET status="VOID",updated_at=NOW(6) WHERE invoice_guid=? AND status IN ("DRAFT","OPEN")',[Uuid::toBinary($invoiceGuid)])===1;
    }

    public function create(string $tenantGuid,int $amountMinor,string $invoiceGuid,string $number): void
    {
        Store::tx(static function()use($tenantGuid,$amountMinor,$invoiceGuid,$number):void{
            $tenant=Store::one('SELECT product_tenant_guid FROM product_tenants WHERE product_tenant_guid=? AND status="ACTIVE" LIMIT 1 FOR UPDATE',[Uuid::toBinary($tenantGuid)]);
            if(!$tenant)throw new \InvalidArgumentException('Select an active product tenant.');
            Store::exec('INSERT INTO invoices(invoice_guid,product_tenant_guid,invoice_number,currency,subtotal_minor,total_minor,status,due_at,created_at,updated_at) VALUES(?,?,?,"INR",?,?,"OPEN",DATE_ADD(NOW(),INTERVAL 7 DAY),NOW(6),NOW(6))',[Uuid::toBinary($invoiceGuid),Uuid::toBinary($tenantGuid),$number,$amountMinor,$amountMinor]);
            Store::exec('INSERT INTO invoice_items(invoice_item_guid,invoice_guid,description,quantity,unit_amount_minor,line_total_minor) VALUES(?,?,"Platform subscription",1,?,?)',[Uuid::toBinary(Uuid::v7()),Uuid::toBinary($invoiceGuid),$amountMinor,$amountMinor]);
        });
    }
}
