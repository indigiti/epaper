<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Billing;

use Digiti\Core\{Store,Uuid};

/** All payment rows are selected under a transaction when reconciling. */
final class PaymentRepository
{
    public function lockEvent(string $guid): ?array
    {
        return Store::one('SELECT webhook_event_guid,provider,verification_status,processing_status,payload FROM webhook_events WHERE webhook_event_guid=? FOR UPDATE',[Uuid::toBinary($guid)]);
    }
    public function matchPayments(string $provider,string $reference): array
    {
        return Store::all('SELECT payment_guid,product_tenant_guid,invoice_guid,amount_minor,currency,status FROM payments WHERE provider=? AND provider_reference=? LIMIT 2 FOR UPDATE',[$provider,$reference]);
    }
    public function lockInvoice(string $guid): ?array
    {
        return Store::one('SELECT invoice_guid,product_tenant_guid,total_minor,currency,status FROM invoices WHERE invoice_guid=? FOR UPDATE',[Uuid::toBinary($guid)]);
    }
    public function markPaymentSuccess(string $guid): void
    {
        $changed=Store::exec('UPDATE payments SET status="SUCCEEDED",updated_at=NOW(6) WHERE payment_guid=? AND status IN ("CREATED","PENDING","PROCESSING")',[Uuid::toBinary($guid)]);
        if($changed!==1)throw new \RuntimeException('Payment state changed during settlement');
    }
    public function markInvoicePaid(string $guid): void
    {
        $changed=Store::exec('UPDATE invoices SET status="PAID",paid_at=NOW(6),updated_at=NOW(6) WHERE invoice_guid=? AND status="OPEN"',[Uuid::toBinary($guid)]);
        if($changed!==1)throw new \RuntimeException('Invoice state changed during settlement');
    }
    public function markEvent(string $guid,string $status,?string $reason=null): void
    {
        Store::exec('UPDATE webhook_events SET processing_status=?,processed_at=NOW(6),error_message=? WHERE webhook_event_guid=?',[$status,$reason,Uuid::toBinary($guid)]);
    }
    public function finishOutbox(string $guid): void
    {
        $changed=Store::exec('UPDATE outbox_events SET status="PROCESSED",processed_at=NOW(6) WHERE outbox_event_guid=? AND status="PROCESSING"',[Uuid::toBinary($guid)]);
        if($changed!==1)throw new \RuntimeException('Outbox lease lost');
    }
}
