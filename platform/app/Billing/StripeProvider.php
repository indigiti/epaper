<?php
namespace Digiti\Billing;
use Digiti\Core\Config;

final class StripeProvider implements PaymentProviderInterface
{
    public function key(): string{return 'stripe';}
    public function createPayment(array $payment,array $context=[]): array
    {
        if(!Config::get('billing.stripe_secret'))return ['ok'=>false,'code'=>'STRIPE_NOT_CONFIGURED'];
        return ['ok'=>false,'code'=>'PROVIDER_NETWORK_ACTION_REQUIRED','message'=>'Stripe adapter is configured; payment-intent creation should be performed by the product/payment service.'];
    }
    public function verifyWebhook(string $payload,array $headers): bool
    {
        $secret=(string)Config::get('billing.stripe_webhook_secret','');
        $header=$headers['stripe-signature']??'';
        if($secret===''||!is_string($header)||$header==='')return false;
        $timestamp=null;$signatures=[];
        foreach(explode(',',$header) as $part){
            [$key,$value]=array_pad(explode('=',trim($part),2),2,'');
            if($key==='t'&&ctype_digit($value))$timestamp=(int)$value;
            if($key==='v1'&&preg_match('/^[a-f0-9]{64}$/i',$value))$signatures[]=strtolower($value);
        }
        // Stripe signatures are valid for five minutes; older replayed payloads fail closed.
        if($timestamp===null||$timestamp<=0||abs(time()-$timestamp)>300||!$signatures)return false;
        $expected=hash_hmac('sha256',$timestamp.'.'.$payload,$secret);
        foreach($signatures as $signature)if(hash_equals($expected,$signature))return true;
        return false;
    }
    public function normalizeWebhook(string $payload,array $headers): array{return json_decode($payload,true)?:[];}
}
