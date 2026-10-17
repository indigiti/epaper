<?php
declare(strict_types=1);
namespace Digiti\Application\Billing;

use Digiti\Core\Store;
use Digiti\Domain\Billing\WebhookEventPolicy;
use Digiti\Infrastructure\Billing\PaymentRepository;

/** Match provider-confirmed successes only to pre-existing, amount-verified payment records. */
final class ReconcileWebhook
{
    public static function execute(string $outboxGuid,string $eventGuid,?PaymentRepository $repo=null): string
    {
        $repo??=new PaymentRepository();
        return Store::tx(static function()use($repo,$outboxGuid,$eventGuid):string{
            $event=$repo->lockEvent($eventGuid);
            if(!$event||$event['verification_status']!=='VERIFIED')
                throw new \RuntimeException('Unverified webhook cannot be reconciled');
            if(in_array($event['processing_status'],['PROCESSED','REVIEW_REQUIRED','IGNORED'],true)){
                $repo->finishOutbox($outboxGuid);return $event['processing_status'];
            }
            $json=json_decode((string)$event['payload'],true);
            if(!is_array($json))throw new \RuntimeException('Stored webhook JSON is invalid');
            $provider=(string)$event['provider'];
            $claim=WebhookEventPolicy::successfulPayment($provider,$json);
            if($claim===null){
                // Unknown/refund/failure events require a separate domain workflow; never pay invoices.
                $repo->markEvent($eventGuid,'REVIEW_REQUIRED','UNSUPPORTED_OR_NON_SUCCESS_EVENT');
                $repo->finishOutbox($outboxGuid);
                return 'REVIEW_REQUIRED';
            }
            $payments=$repo->matchPayments($provider,$claim['provider_reference']);
            if(count($payments)!==1){
                $repo->markEvent($eventGuid,'REVIEW_REQUIRED','UNMATCHED_OR_AMBIGUOUS_PAYMENT');
                $repo->finishOutbox($outboxGuid);
                return 'REVIEW_REQUIRED';
            }
            $payment=$payments[0];
            if((int)$payment['amount_minor']!==$claim['amount_minor']
                ||strtoupper((string)$payment['currency'])!==$claim['currency']){
                $repo->markEvent($eventGuid,'REVIEW_REQUIRED','PAYMENT_AMOUNT_OR_CURRENCY_MISMATCH');
                $repo->finishOutbox($outboxGuid);
                return 'REVIEW_REQUIRED';
            }
            if(!in_array($payment['status'],['CREATED','PENDING','PROCESSING','SUCCEEDED'],true)){
                $repo->markEvent($eventGuid,'REVIEW_REQUIRED','PAYMENT_STATE_INVALID');
                $repo->finishOutbox($outboxGuid);
                return 'REVIEW_REQUIRED';
            }
            $invoice=null;
            if(!empty($payment['invoice_guid'])){
                $invoice=$repo->lockInvoice($payment['invoice_guid']);
                if(!$invoice||$invoice['product_tenant_guid']!==$payment['product_tenant_guid']
                    ||(int)$invoice['total_minor']!==$claim['amount_minor']
                    ||strtoupper((string)$invoice['currency'])!==$claim['currency']
                    ||!in_array($invoice['status'],['OPEN','PAID'],true)){
                    $repo->markEvent($eventGuid,'REVIEW_REQUIRED','INVOICE_TENANT_OR_FINANCIAL_MISMATCH');
                    $repo->finishOutbox($outboxGuid);
                    return 'REVIEW_REQUIRED';
                }
            }
            if($invoice&&$invoice['status']==='PAID'&&$payment['status']!=='SUCCEEDED'){
                // A different payment may have paid this invoice already; never apply a second settlement silently.
                $repo->markEvent($eventGuid,'REVIEW_REQUIRED','INVOICE_ALREADY_PAID_BY_ANOTHER_PAYMENT');
                $repo->finishOutbox($outboxGuid);
                return 'REVIEW_REQUIRED';
            }
            if($payment['status']!=='SUCCEEDED')$repo->markPaymentSuccess($payment['payment_guid']);
            if($invoice&&$invoice['status']!=='PAID')$repo->markInvoicePaid($invoice['invoice_guid']);
            $repo->markEvent($eventGuid,'PROCESSED');
            $repo->finishOutbox($outboxGuid);
            return 'PROCESSED';
        });
    }
}
