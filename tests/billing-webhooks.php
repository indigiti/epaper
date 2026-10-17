<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    if(!str_starts_with($class,'Digiti\\'))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,7)).'.php';
    if(is_file($file))require_once $file;
});
use Digiti\Core\Config;
use Digiti\Application\Billing\{WebhookFailure,WebhookIngress};
use Digiti\Infrastructure\Billing\WebhookRepository;
use Digiti\Domain\Billing\WebhookEventPolicy as Policy;
$reflection=new ReflectionClass(Config::class);
$reflection->getProperty('data')->setValue(null,['billing'=>['stripe_webhook_secret'=>'fixture-signing-secret','razorpay_key_secret'=>'fixture-razorpay-secret']]);
$failed=[];$check=static function(bool $ok,string $name)use(&$failed):void{echo ($ok?'PASS':'FAIL')." — $name\n";if(!$ok)$failed[]=$name;};
$repo=new class extends WebhookRepository {
    public array $receipts=[];
    public bool $duplicate=false;
    public function receive(string $provider,string $reference,string $raw): bool {
        $this->receipts[]=[$provider,$reference,strlen($raw)];
        return $this->duplicate;
    }
};
$event=['id'=>'evt_fixture_123','type'=>'payment_intent.succeeded','data'=>['object'=>['object'=>'payment_intent','id'=>'pi_fixture_123','status'=>'succeeded','amount_received'=>1900,'currency'=>'inr']]];
$raw=json_encode($event,JSON_THROW_ON_ERROR);
$timestamp=time();
$sig=hash_hmac('sha256',$timestamp.'.'.$raw,'fixture-signing-secret');
$headers=['stripe-signature'=>"t=$timestamp,v1=$sig"];
$response=WebhookIngress::receive('stripe',$raw,$headers,$repo);
$check($response['ok']&&!$response['duplicate']&&$response['queued'],'Stripe verified receipt is queued');
$check(count($repo->receipts)===1&&$repo->receipts[0][1]==='evt_fixture_123','signed event ID passed to persistence');
$repo->duplicate=true;
$check(WebhookIngress::receive('stripe',$raw,$headers,$repo)['duplicate']===true,'duplicate receipt is acknowledged without new queue');
$repo->duplicate=false;
$testError=static function(string $provider,string $payload,array $headers,string $expected)use($repo):bool{
    try{WebhookIngress::receive($provider,$payload,$headers,$repo);return false;}
    catch(WebhookFailure $e){return $e->errorCode()===$expected;}
};
$check($testError('stripe',$raw,['stripe-signature'=>"t=".($timestamp-301).",v1=".hash_hmac('sha256',($timestamp-301).'.'.$raw,'fixture-signing-secret')],'WEBHOOK_SIGNATURE_INVALID'),'expired Stripe signature rejected');
$check($testError('stripe',$raw,['stripe-signature'=>"t=$timestamp,v1=".str_repeat('0',64)],'WEBHOOK_SIGNATURE_INVALID'),'forged Stripe signature rejected');
$check($testError('unknown',$raw,$headers,'WEBHOOK_PROVIDER_UNSUPPORTED'),'unsupported provider rejected');
$check($testError('stripe','',$headers,'WEBHOOK_PAYLOAD_INVALID'),'missing body rejected');
$missingId=$event;unset($missingId['id']);$rawNoId=json_encode($missingId,JSON_THROW_ON_ERROR);
$check($testError('stripe',$rawNoId,['stripe-signature'=>"t=$timestamp,v1=".hash_hmac('sha256',$timestamp.'.'.$rawNoId,'fixture-signing-secret')],'WEBHOOK_EVENT_ID_REQUIRED'),'missing signed event ID rejected');
$claim=Policy::successfulPayment('stripe',$event);
$check($claim!==null&&$claim['amount_minor']===1900&&$claim['currency']==='INR','Stripe captured amount/currency normalized');
$bad=$event;$bad['data']['object']['amount_received']='1900';
$check(Policy::successfulPayment('stripe',$bad)===null,'string amount cannot settle invoices');
$bad=$event;$bad['data']['object']['status']='requires_capture';
$check(Policy::successfulPayment('stripe',$bad)===null,'incomplete payment cannot settle invoices');
$razor=['event'=>'payment.captured','payload'=>['payment'=>['entity'=>['id'=>'pay_fixture_123','status'=>'captured','amount'=>1950,'currency'=>'INR']]]];
$rr=json_encode($razor,JSON_THROW_ON_ERROR);$rh=['x-razorpay-event-id'=>'evt_razor_fixture_123','x-razorpay-signature'=>hash_hmac('sha256',$rr,'fixture-razorpay-secret')];
$repo->duplicate=false;
$check(WebhookIngress::receive('razorpay',$rr,$rh,$repo)['ok']===true,'Razorpay verified event ID accepted');
$check($testError('razorpay',$rr,['x-razorpay-signature'=>$rh['x-razorpay-signature']],'WEBHOOK_EVENT_ID_REQUIRED'),'Razorpay event ID header mandatory');
$check(Policy::successfulPayment('razorpay',$razor)['amount_minor']===1950,'Razorpay captured amount normalized');
exit($failed?1:0);
