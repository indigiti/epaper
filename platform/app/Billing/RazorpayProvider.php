<?php
namespace Digiti\Billing;
use Digiti\Core\Config;

final class RazorpayProvider implements PaymentProviderInterface
{
    public function key(): string{return 'razorpay';}
    public function createPayment(array $payment,array $context=[]): array
    {
        $key=Config::get('billing.razorpay_key_id'); $secret=Config::get('billing.razorpay_key_secret');
        if(!$key||!$secret)return ['ok'=>false,'code'=>'RAZORPAY_NOT_CONFIGURED'];
        return ['ok'=>false,'code'=>'PROVIDER_NETWORK_ACTION_REQUIRED','message'=>'Use the product/payment service to create the provider order. Credentials are configured and the adapter is ready.'];
    }
    public function verifyWebhook(string $payload,array $headers): bool
    {
        $secret=(string)Config::get('billing.razorpay_key_secret','');$sig=$headers['x-razorpay-signature']??'';
        return $secret!==''&&$sig!==''&&hash_equals(hash_hmac('sha256',$payload,$secret),$sig);
    }
    public function normalizeWebhook(string $payload,array $headers): array{return json_decode($payload,true)?:[];}
}
