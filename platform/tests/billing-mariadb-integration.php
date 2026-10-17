<?php
declare(strict_types=1);
/**
 * Disposable CI MariaDB only. Requires a dedicated test database and fixture-only config.
 * Never run this script against a production database.
 */
if(getenv('EPDI_BILLING_INTEGRATION')!=='disposable-ci') {
    fwrite(STDERR,"This test requires EPDI_BILLING_INTEGRATION=disposable-ci.\n");exit(2);
}
$root=require dirname(__DIR__).'/app/bootstrap.php';
use Digiti\Core\{Store,Uuid};
use Digiti\Infrastructure\Billing\WebhookRepository;
use Digiti\Application\Billing\ReconcileWebhook;

$fail=[];$check=static function(bool $ok,string $label)use(&$fail):void{
    echo ($ok?'PASS':'FAIL')." — ".$label."\n";
    if(!$ok)$fail[]=$label;
};
$id=static fn():string=>Uuid::v7();
$bin=static fn(string $uuid):string=>Uuid::toBinary($uuid);

$organisation=$id();$product=$id();$tenant=$id();
Store::exec('INSERT INTO organisations(organisation_guid,organisation_code,legal_name,display_name,status,created_at,updated_at) VALUES(?,"CI_ORG","CI test organisation","CI organisation","ACTIVE",NOW(6),NOW(6))',[$bin($organisation)]);
Store::exec('INSERT INTO products(product_guid,product_key,name,status,created_at,updated_at) VALUES(?,"CI_EPAPER","CI SaaS","ACTIVE",NOW(6),NOW(6))',[$bin($product)]);
Store::exec('INSERT INTO product_tenants(product_tenant_guid,organisation_guid,product_guid,display_name,status,created_at,updated_at) VALUES(?,?,?,"CI tenant","ACTIVE",NOW(6),NOW(6))',[$bin($tenant),$bin($organisation),$bin($product)]);

$invoice=$id();$payment=$id();$ref='pi_ci_capture_001';
Store::exec('INSERT INTO invoices(invoice_guid,product_tenant_guid,invoice_number,currency,subtotal_minor,total_minor,status,due_at,created_at,updated_at) VALUES(?,?,"CI-INVOICE-001","INR",1900,1900,"OPEN",NOW(6),NOW(6),NOW(6))',[$bin($invoice),$bin($tenant)]);
Store::exec('INSERT INTO payments(payment_guid,product_tenant_guid,invoice_guid,provider,amount_minor,currency,status,provider_reference,created_at,updated_at) VALUES(?,?,?,"stripe",1900,"INR","PENDING",?,NOW(6),NOW(6))',[$bin($payment),$bin($tenant),$bin($invoice),$ref]);

$receipt=new WebhookRepository();
$makeEvent=static function(string $eventId,string $reference,int $amount) : array {
    return [
        'id'=>$eventId,'type'=>'payment_intent.succeeded',
        'data'=>['object'=>['object'=>'payment_intent','id'=>$reference,'status'=>'succeeded','amount_received'=>$amount,'currency'=>'inr']]
    ];
};
$getOutbox=static function(string $eventId): array {
    $row=Store::one('SELECT webhook_event_guid FROM webhook_events WHERE provider="stripe" AND provider_event_reference=?',[$eventId]);
    if(!$row)throw new RuntimeException('Missing fixture webhook event');
    $outbox=Store::one('SELECT outbox_event_guid FROM outbox_events WHERE aggregate_guid=?',[$bin=Uuid::toBinary($row['webhook_event_guid'])]);
    if(!$outbox)throw new RuntimeException('Missing atomic outbox event');
    Store::exec('UPDATE outbox_events SET status="PROCESSING" WHERE outbox_event_guid=?',[Uuid::toBinary($outbox['outbox_event_guid'])]);
    return [$row['webhook_event_guid'],$outbox['outbox_event_guid']];
};

$event='evt_ci_capture_001';$raw=json_encode($makeEvent($event,$ref,1900),JSON_THROW_ON_ERROR);
$check($receipt->receive('stripe',$event,$raw)===false,'first valid receipt inserted');
$check($receipt->receive('stripe',$event,$raw)===true,'same provider event deduplicated');
$check((int)Store::value('SELECT COUNT(*) FROM webhook_events WHERE provider="stripe" AND provider_event_reference=?',[$event])===1,'one durable webhook');
$check((int)Store::value('SELECT COUNT(*) FROM outbox_events WHERE event_type="payment.webhook.received"')===1,'one outbox receipt for retry');
[$webhook,$outbox]=$getOutbox($event);
$check(ReconcileWebhook::execute($outbox,$webhook)==='PROCESSED','matching provider receipt reconciled');
$check(Store::value('SELECT status FROM payments WHERE payment_guid=?',[$bin($payment)])==='SUCCEEDED','linked payment settled');
$check(Store::value('SELECT status FROM invoices WHERE invoice_guid=?',[$bin($invoice)])==='PAID','matching invoice marked paid');
$check(Store::value('SELECT status FROM outbox_events WHERE outbox_event_guid=?',[$bin($outbox)])==='PROCESSED','outbox ack committed with settlement');

$badEvent='evt_ci_amount_wrong';
$rawBad=json_encode($makeEvent($badEvent,$ref,1850),JSON_THROW_ON_ERROR);
$check($receipt->receive('stripe',$badEvent,$rawBad)===false,'mismatched amount still gets durable receipt');
[$badWebhook,$badOutbox]=$getOutbox($badEvent);
$check(ReconcileWebhook::execute($badOutbox,$badWebhook)==='REVIEW_REQUIRED','amount mismatch sent to manual review');
$check(Store::value('SELECT error_message FROM webhook_events WHERE webhook_event_guid=?',[$bin($badWebhook)])==='PAYMENT_AMOUNT_OR_CURRENCY_MISMATCH','mismatch reason preserved');
$check(Store::value('SELECT total_minor FROM invoices WHERE invoice_guid=?',[$bin($invoice)])==1900,'invoice total unchanged by mismatch');

$unknownEvent='evt_ci_unmatched';
$rawUnknown=json_encode($makeEvent($unknownEvent,'pi_ci_unknown',1900),JSON_THROW_ON_ERROR);
$receipt->receive('stripe',$unknownEvent,$rawUnknown);
[$unknownWebhook,$unknownOutbox]=$getOutbox($unknownEvent);
$check(ReconcileWebhook::execute($unknownOutbox,$unknownWebhook)==='REVIEW_REQUIRED','unmatched provider reference never auto-settles');
$check((int)Store::value('SELECT COUNT(*) FROM webhook_events WHERE processing_status="REVIEW_REQUIRED"')===2,'exception review queue retains both cases');

// Recover a crashed processing lease without pretending the webhook was processed.
$lease=$id();
Store::exec('INSERT INTO outbox_events(outbox_event_guid,event_type,payload,status,attempts,available_at,created_at) VALUES(?,"payment.webhook.received",?,"PROCESSING",2,DATE_SUB(NOW(6),INTERVAL 1 MINUTE),NOW(6))',[$bin($lease),'{}']);
$exhausted=$id();
Store::exec('INSERT INTO outbox_events(outbox_event_guid,event_type,payload,status,attempts,available_at,created_at) VALUES(?,"payment.webhook.received",?,"PROCESSING",5,DATE_SUB(NOW(6),INTERVAL 1 MINUTE),NOW(6))',[$bin($exhausted),'{}']);
$recovered=\\Digiti\\Application\\Operations\\QueueLeaseRecovery::recover();
$check($recovered['outbox']>=2,'expired in-flight outbox receipts recovered');
$check(Store::value('SELECT status FROM outbox_events WHERE outbox_event_guid=?',[$bin($lease)])==='PENDING','crashed receipt restored for retry');
$check(Store::value('SELECT status FROM outbox_events WHERE outbox_event_guid=?',[$bin($exhausted)])==='FAILED','attempt limit moves receipt to failed queue');

echo "MariaDB billing integration checks complete.\n";
exit($fail?1:0);
