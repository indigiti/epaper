<?php
declare(strict_types=1);
namespace Digiti\Domain\Billing;

/** Pure, provider-specific event shape and settlement invariants. */
final class WebhookEventPolicy
{
    public static function eventReference(string $provider,array $event,array $headers): ?string
    {
        $id=$provider==='stripe'?($event['id']??null):($provider==='razorpay'?($headers['x-razorpay-event-id']??null):null);
        return is_string($id)&&strlen($id)>=6&&strlen($id)<=190&&preg_match('/^[a-zA-Z0-9_-]+$/',$id)?$id:null;
    }

    /** Returns a validated payment-success claim, or null when settlement is unsupported. */
    public static function successfulPayment(string $provider,array $event): ?array
    {
        if($provider==='stripe'){
            if(($event['type']??null)!=='payment_intent.succeeded')return null;
            $payment=$event['data']['object']??null;
            if(!is_array($payment)||($payment['object']??null)!=='payment_intent'||($payment['status']??null)!=='succeeded')return null;
            $ref=$payment['id']??null;
            $amount=$payment['amount_received']??null;
            $currency=$payment['currency']??null;
        } elseif($provider==='razorpay'){
            if(($event['event']??null)!=='payment.captured')return null;
            $payment=$event['payload']['payment']['entity']??null;
            if(!is_array($payment)||($payment['status']??null)!=='captured')return null;
            $ref=$payment['id']??null;
            $amount=$payment['amount']??null;
            $currency=$payment['currency']??null;
        } else return null;
        if(!is_string($ref)||$ref===''||strlen($ref)>190||!preg_match('/^[a-zA-Z0-9_-]+$/',$ref))return null;
        if(!is_int($amount)||$amount<0)return null;
        if(!is_string($currency)||!preg_match('/^[A-Za-z]{3}$/',$currency))return null;
        return ['provider_reference'=>$ref,'amount_minor'=>$amount,'currency'=>strtoupper($currency)];
    }
}
