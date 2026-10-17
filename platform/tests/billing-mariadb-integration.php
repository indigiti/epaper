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
try{
    $receipt->receive('stripe',$event,json_encode($makeEvent($event,$ref,1850),JSON_THROW_ON_ERROR));
    $conflictDenied=false;
}catch(\Digiti\Application\Billing\WebhookFailure $e){
    $conflictDenied=$e->errorCode()==='WEBHOOK_EVENT_CONFLICT';
}
$check($conflictDenied,'same provider event ID with changed payload is rejected');
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

// A second provider success must not silently settle an invoice that is already paid.
$secondPayment=$id();$secondRef='pi_ci_duplicate_invoice_002';
Store::exec('INSERT INTO payments(payment_guid,product_tenant_guid,invoice_guid,provider,amount_minor,currency,status,provider_reference,created_at,updated_at) VALUES(?,?,?,"stripe",1900,"INR","PENDING",?,NOW(6),NOW(6))',[$bin($secondPayment),$bin($tenant),$bin($invoice),$secondRef]);
$secondEvt='evt_ci_duplicate_invoice_002';
$receipt->receive('stripe',$secondEvt,json_encode($makeEvent($secondEvt,$secondRef,1900),JSON_THROW_ON_ERROR));
[$secondWebhook,$secondOutbox]=$getOutbox($secondEvt);
$check(ReconcileWebhook::execute($secondOutbox,$secondWebhook)==='REVIEW_REQUIRED','second attempt against paid invoice goes to review');
$check(Store::value('SELECT status FROM payments WHERE payment_guid=?',[$bin($secondPayment)])==='PENDING','second payment remains unsettled by Platform');

// Invoice void must be an atomic legal state transition, never pay-to-void.
$voidPaidDenied=false;
try{\Digiti\Application\Billing\VoidInvoice::execute($invoice);}
catch(RuntimeException){$voidPaidDenied=true;}
$check($voidPaidDenied,'paid invoice cannot be voided after settlement');
$check(Store::value('SELECT status FROM invoices WHERE invoice_guid=?',[$bin($invoice)])==='PAID','paid invoice remains immutable');
$openInvoice=$id();
(new \Digiti\Infrastructure\Billing\InvoiceRepository())->create($tenant,2500,$openInvoice,'CI-VOID-OPEN-002');
\Digiti\Application\Billing\VoidInvoice::execute($openInvoice);
$check(Store::value('SELECT status FROM invoices WHERE invoice_guid=?',[$bin($openInvoice)])==='VOID','open invoice can be voided atomically');

// Recover a crashed processing lease without pretending the webhook was processed.
$lease=$id();
Store::exec('INSERT INTO outbox_events(outbox_event_guid,event_type,payload,status,attempts,available_at,created_at) VALUES(?,"payment.webhook.received",?,"PROCESSING",2,DATE_SUB(NOW(6),INTERVAL 1 MINUTE),NOW(6))',[$bin($lease),'{}']);
$exhausted=$id();
Store::exec('INSERT INTO outbox_events(outbox_event_guid,event_type,payload,status,attempts,available_at,created_at) VALUES(?,"payment.webhook.received",?,"PROCESSING",5,DATE_SUB(NOW(6),INTERVAL 1 MINUTE),NOW(6))',[$bin($exhausted),'{}']);
$recovered=\Digiti\Application\Operations\QueueLeaseRecovery::recover();
$check($recovered['outbox']>=2,'expired in-flight outbox receipts recovered');
$check(Store::value('SELECT status FROM outbox_events WHERE outbox_event_guid=?',[$bin($lease)])==='PENDING','crashed receipt restored for retry');
$check(Store::value('SELECT status FROM outbox_events WHERE outbox_event_guid=?',[$bin($exhausted)])==='FAILED','attempt limit moves receipt to failed queue');

// Subscription Admin CRUD and authoritative access behavior (same disposable MariaDB).
$plan=$id();
Store::exec('INSERT INTO plans(plan_guid,product_guid,plan_key,name,status,created_at,updated_at) VALUES(?,?,"CI_BUSINESS","CI Business","ACTIVE",NOW(6),NOW(6))',[$bin($plan),$bin($product)]);
$subscription=\Digiti\Application\Subscriptions\ManageSubscription::activate($tenant,$plan);
$check(\Digiti\Core\Uuid::isValid($subscription),'Admin activation returns subscription GUID');
$check(\Digiti\Application\Subscriptions\SubscriptionAccess::payload($tenant)['service_access_state']==='FULL','new active subscription grants full access');
try{\Digiti\Application\Subscriptions\ManageSubscription::activate($tenant,$plan);$duplicateSubscriptionDenied=false;}
catch(\DomainException){$duplicateSubscriptionDenied=true;}
$check($duplicateSubscriptionDenied,'duplicate concurrent-active subscription refused');

$otherProduct=$id();$otherPlan=$id();
Store::exec('INSERT INTO products(product_guid,product_key,name,status,created_at,updated_at) VALUES(?,"CI_OTHER_PRODUCT","CI Other Product","ACTIVE",NOW(6),NOW(6))',[$bin($otherProduct)]);
Store::exec('INSERT INTO plans(plan_guid,product_guid,plan_key,name,status,created_at,updated_at) VALUES(?,?,"CI_OTHER_PLAN","CI Other Plan","ACTIVE",NOW(6),NOW(6))',[$bin($otherPlan),$bin($otherProduct)]);
try{\Digiti\Application\Subscriptions\ManageSubscription::activate($tenant,$otherPlan);$crossProductDenied=false;}
catch(\DomainException){$crossProductDenied=true;}
$check($crossProductDenied,'wrong-product plan rejected');

$future='2030-12-31T12:00';
$fields=[
    'version'=>'1','status'=>'ACTIVE','billing_state'=>'PAST_DUE',
    'service_access_state'=>'FULL','current_period_end'=>$future
];
\Digiti\Application\Subscriptions\ManageSubscription::update($subscription,$fields);
$check(\Digiti\Application\Subscriptions\SubscriptionAccess::payload($tenant)['service_access_state']==='RESTRICTED','past-due access downgraded even when stored FULL');
try{\Digiti\Application\Subscriptions\ManageSubscription::update($subscription,$fields);$staleDenied=false;}
catch(\DomainException){$staleDenied=true;}
$check($staleDenied,'stale Admin subscription form cannot overwrite newer edit');
$fields['version']='2';$fields['billing_state']='ACTIVE';
\Digiti\Application\Subscriptions\ManageSubscription::update($subscription,$fields);
$check(\Digiti\Application\Subscriptions\SubscriptionAccess::payload($tenant)['service_access_state']==='FULL','active billing restores effective full access');

Store::exec('UPDATE organisations SET status="SUSPENDED" WHERE organisation_guid=?',[$bin($organisation)]);
$check(\Digiti\Application\Subscriptions\SubscriptionAccess::payload($tenant)['service_access_state']==='BLOCKED','parent organisation suspension blocks access without waiting on cache');
Store::exec('UPDATE organisations SET status="ACTIVE" WHERE organisation_guid=?',[$bin($organisation)]);
Store::exec('UPDATE subscriptions SET current_period_end=DATE_SUB(NOW(6), INTERVAL 1 MINUTE) WHERE subscription_guid=?',[$bin($subscription)]);
$check(\Digiti\Application\Subscriptions\SubscriptionAccess::payload($tenant)['service_access_state']==='BLOCKED','period expiry blocks access despite stored FULL');

echo "MariaDB billing integration checks complete.\n";
exit($fail?1:0);
