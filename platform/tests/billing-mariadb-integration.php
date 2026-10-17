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
// Batched Admin read model: complete price mapping for multiple products and plans.
$catalogPrice1=$id();$catalogPrice2=$id();
Store::exec('INSERT INTO prices(price_guid,plan_guid,currency,amount_minor,billing_interval,interval_count,status,created_at) VALUES(?,?,"INR",1500,"MONTH",1,"ACTIVE",NOW(6))',[$bin($catalogPrice1),$bin($plan)]);
Store::exec('INSERT INTO prices(price_guid,plan_guid,currency,amount_minor,billing_interval,interval_count,status,created_at) VALUES(?,?,"INR",2500,"YEAR",1,"ACTIVE",NOW(6))',[$bin($catalogPrice2),$bin($otherPlan)]);
$catalog=\Digiti\Infrastructure\Commercial\CatalogReadRepository::snapshot();
$check(count($catalog['plans'])>=2,'batched Commercial catalog returns multiple plans');
$check(count($catalog['prices_by_plan'][$plan]??[])===1,'first plan price grouped by normalized GUID');
$check(count($catalog['prices_by_plan'][$otherPlan]??[])===1,'second product price grouped without extra SELECT');
$counts=\Digiti\Infrastructure\Reporting\AdminDashboardRepository::counts();
$check(($counts['Organisations']??0)>=1,'Admin dashboard batched organisation count');
$check(($counts['Product Tenants']??0)>=1,'Admin dashboard batched tenant count');
$check(($counts['Subscriptions']??0)>=1,'Admin dashboard batched subscription count');



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

// Usage metering is one atomic upsert per tenant/product/feature/month.
$featureId=$id();$wrongFeature=$id();
Store::exec('INSERT INTO features(feature_guid,product_guid,feature_key,name,created_at) VALUES(?,?,"ci_pdf_pages","CI PDF Pages",NOW(6))',[$bin($featureId),$bin($product)]);
Store::exec('INSERT INTO features(feature_guid,product_guid,feature_key,name,created_at) VALUES(?,?,"ci_other_pages","CI Other Product Pages",NOW(6))',[$bin($wrongFeature),$bin($otherProduct)]);
\Digiti\Application\Metering\RecordUsage::execute($tenant,['feature_key'=>'ci_pdf_pages','delta'=>'2.5','period_key'=>'2026-10']);
\Digiti\Application\Metering\RecordUsage::execute($tenant,['feature_key'=>'ci_pdf_pages','delta'=>'0.25','period_key'=>'2026-10']);
$used=(float)Store::value('SELECT used_value FROM usage_meters WHERE product_tenant_guid=? AND feature_guid=? AND period_key="2026-10"',[$bin($tenant),$bin($featureId)]);
$check(abs($used-2.75)<0.00001,'repeated usage increments share one atomic monthly meter');
$check((int)Store::value('SELECT COUNT(*) FROM usage_meters WHERE product_tenant_guid=? AND feature_guid=?',[$bin($tenant),$bin($featureId)])===1,'no duplicate counter rows for same meter');
\Digiti\Application\Metering\RecordUsage::execute($tenant,['feature_key'=>'ci_pdf_pages','delta'=>'-5','period_key'=>'2026-10']);
$check((float)Store::value('SELECT used_value FROM usage_meters WHERE product_tenant_guid=? AND feature_guid=?',[$bin($tenant),$bin($featureId)])===0.0,'negative correction cannot create negative usage');
try{\Digiti\Application\Metering\RecordUsage::execute($tenant,['feature_key'=>'ci_other_pages','delta'=>'1','period_key'=>'2026-10']);$meterCrossProductDenied=false;}
catch(\DomainException){$meterCrossProductDenied=true;}
$check($meterCrossProductDenied,'cross-product feature usage denied');
// Stable event GUID gives at-most-once metering even if HTTP client retries.
$stableEvent=$id();
$eventPayload=['feature_key'=>'ci_pdf_pages','delta'=>'3.5','period_key'=>'2026-11','event_guid'=>$stableEvent];
$check(\Digiti\Application\Metering\RecordUsage::execute($tenant,$eventPayload)===true,'first usage event applied');
$check(\Digiti\Application\Metering\RecordUsage::execute($tenant,$eventPayload)===false,'same usage event retried without increment');
$check((float)Store::value('SELECT used_value FROM usage_meters WHERE product_tenant_guid=? AND feature_guid=? AND period_key="2026-11"',[$bin($tenant),$bin($featureId)])===3.5,'usage counter changed exactly once');
$eventPayload['delta']='7.0';
try{\Digiti\Application\Metering\RecordUsage::execute($tenant,$eventPayload);$conflict=false;}
catch(\DomainException $e){$conflict=$e->getMessage()==='USAGE_EVENT_CONFLICT';}
$check($conflict,'reused usage ID with changed delta rejected');
$check((float)Store::value('SELECT used_value FROM usage_meters WHERE product_tenant_guid=? AND feature_guid=? AND period_key="2026-11"',[$bin($tenant),$bin($featureId)])===3.5,'rejected conflicting event never updates usage');
$check((int)Store::value('SELECT COUNT(*) FROM usage_events WHERE event_guid=?',[$bin($stableEvent)])===1,'one persisted event ledger record');


// Tenant lifecycle with live foreign-key related domain, in isolated CI database.
Store::exec('UPDATE subscriptions SET current_period_end=DATE_ADD(NOW(6), INTERVAL 1 MONTH) WHERE subscription_guid=?',[$bin($subscription)]);
$applicationGuid=$id();$domainGuid=$id();
Store::exec('INSERT INTO applications(application_guid,product_guid,application_key,name,base_url,status,created_at,updated_at) VALUES(?,?,"ci_epaper_app","CI App","https://ci.test.invalid","ACTIVE",NOW(6),NOW(6))',[$bin($applicationGuid),$bin($product)]);
Store::exec('INSERT INTO domains(domain_guid,organisation_guid,product_guid,application_guid,product_tenant_guid,hostname,normalized_hostname,status,is_primary,verification_token,created_at,updated_at) VALUES(?,?,?,?,?,"ci.epaper.invalid","ci.epaper.invalid","ACTIVE",1,"test-token",NOW(6),NOW(6))',[
    $bin($domainGuid),$bin($organisation),$bin($product),$bin($applicationGuid),$bin($tenant)
]);
// Domain removed through Admin application service cannot remain routable.
$removedDomain=$id();
Store::exec('INSERT INTO domains(domain_guid,organisation_guid,product_guid,application_guid,product_tenant_guid,hostname,normalized_hostname,status,is_primary,verification_token,created_at,updated_at) VALUES(?,?,?,?,?,"ci.removed.invalid","ci.removed.invalid","SUSPENDED",1,"test-token-2",NOW(6),NOW(6))',[
    $bin($removedDomain),$bin($organisation),$bin($product),$bin($applicationGuid),$bin($tenant)
]);
\Digiti\Application\Routing\RemoveDomain::execute($removedDomain);
$check(Store::value('SELECT status FROM domains WHERE domain_guid=?',[$bin($removedDomain)])==='REMOVED','domain lifecycle removal persists');
$check((int)Store::value('SELECT is_primary FROM domains WHERE domain_guid=?',[$bin($removedDomain)])===0,'removed domain primary routing disabled');

\Digiti\Application\Tenants\ManageTenant::status($tenant,'SUSPENDED');
$check(\Digiti\Application\Subscriptions\SubscriptionAccess::payload($tenant)['service_access_state']==='BLOCKED','Admin tenant suspension revokes fresh subscription access');
\Digiti\Application\Tenants\ManageTenant::status($tenant,'ACTIVE');
$check(\Digiti\Application\Subscriptions\SubscriptionAccess::payload($tenant)['service_access_state']==='FULL','active tenant may resume valid subscription');
\Digiti\Application\Tenants\ManageTenant::remove($tenant);
$check(Store::value('SELECT status FROM product_tenants WHERE product_tenant_guid=?',[$bin($tenant)])==='REMOVED','tenant removal persisted');
$check(Store::value('SELECT status FROM domains WHERE domain_guid=?',[$bin($domainGuid)])==='REMOVED','tenant removal deactivates associated domain');
$check((int)Store::value('SELECT is_primary FROM domains WHERE domain_guid=?',[$bin($domainGuid)])===0,'removed domain is not primary');
$check(\Digiti\Application\Subscriptions\SubscriptionAccess::payload($tenant)['service_access_state']==='BLOCKED','removed tenant cannot use subscription');
try{\Digiti\Application\Tenants\ManageTenant::status($tenant,'ACTIVE');$revived=false;}
catch(\DomainException){$revived=true;}
$check($revived,'removed tenant cannot be reactivated by stale POST');

// Platform → ePaper durable invalidation integration with an actual EPAPER product.
$epOrg=$id();$epProduct=$id();$epTenant=$id();$epApp=$id();$epDomain=$id();
Store::exec('INSERT INTO organisations(organisation_guid,organisation_code,legal_name,display_name,status,created_at,updated_at) VALUES(?,"CI_EPAPER_ORG","CI Publishing","CI Publishing","ACTIVE",NOW(6),NOW(6))',[$bin($epOrg)]);
Store::exec('INSERT INTO products(product_guid,product_key,name,status,created_at,updated_at) VALUES(?,"EPAPER","ePaper SaaS","ACTIVE",NOW(6),NOW(6))',[$bin($epProduct)]);
Store::exec('INSERT INTO applications(application_guid,product_guid,application_key,name,base_url,status,created_at,updated_at) VALUES(?,?,"ci_epaper_product","CI EPAPER","https://epaper.invalid","ACTIVE",NOW(6),NOW(6))',[$bin($epApp),$bin($epProduct)]);
Store::exec('INSERT INTO product_tenants(product_tenant_guid,organisation_guid,product_guid,display_name,status,created_at,updated_at) VALUES(?,?,?,"CI ePaper tenant","ACTIVE",NOW(6),NOW(6))',[$bin($epTenant),$bin($epOrg),$bin($epProduct)]);
Store::exec('INSERT INTO domains(domain_guid,organisation_guid,product_guid,application_guid,product_tenant_guid,hostname,normalized_hostname,status,is_primary,verification_token,created_at,updated_at) VALUES(?,?,?,?,?,"paper.ci.invalid","paper.ci.invalid","ACTIVE",1,"ci-validation",NOW(6),NOW(6))',[$bin($epDomain),$bin($epOrg),$bin($epProduct),$bin($epApp),$bin($epTenant)]);
\Digiti\Core\Store::tx(static function()use($epTenant):void{
    \Digiti\Application\Integration\CacheInvalidationOutbox::stageForTenant($epTenant);
});
$delivery=Store::one('SELECT outbox_event_guid,tenant_guid,payload,status FROM outbox_events WHERE event_type="cache.invalidate" ORDER BY created_at DESC LIMIT 1');
$envelope=is_array($delivery)?json_decode((string)$delivery['payload'],true):null;
$check(is_array($envelope)&&$envelope['tenant_guid']===$epTenant,'invalidation event bound to ePaper tenant');
$check(($envelope['hostnames']??[])===['paper.ci.invalid'],'invalidation payload contains only linked domain');
$check(($envelope['event_id']??'')===$delivery['outbox_event_guid'],'signed event ID matches durable outbox GUID');
$check($delivery['status']==='PENDING','delivery is pending until authenticated receiver ACK');
$countBefore=(int)Store::value('SELECT COUNT(*) FROM outbox_events WHERE event_type="cache.invalidate"');
try{
    Store::tx(static function()use($epTenant):void{
        \Digiti\Application\Integration\CacheInvalidationOutbox::stageForTenant($epTenant);
        throw new \RuntimeException('rollback probe');
    });
}catch(\RuntimeException){}
$check((int)Store::value('SELECT COUNT(*) FROM outbox_events WHERE event_type="cache.invalidate"')===$countBefore,'event insert rolled back with parent transaction');

echo "MariaDB billing integration checks complete.\n";
exit($fail?1:0);
