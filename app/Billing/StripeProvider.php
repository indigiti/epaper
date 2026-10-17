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
        $secret=(string)Config::get('billing.stripe_webhook_secret','');$header=$headers['stripe-signature']??'';if(!$secret||!$header)return false;
        $parts=[];foreach(explode(',',$header)as$p){[$k,$v]=array_pad(explode('=',$p,2),2,'');$parts[$k]=$v;}
        if(empty($parts['t'])||empty($parts['v1']))return false;$expected=hash_hmac('sha256',$parts['t'].'.'.$payload,$secret);return hash_equals($expected,$parts['v1']);
    }
    public function normalizeWebhook(string $payload,array $headers): array{return json_decode($payload,true)?:[];}
}
