<?php
namespace Digiti\Billing;

final class ProviderFactory
{
    public static function make(string $key): PaymentProviderInterface
    {
        return match(strtolower($key)){'razorpay'=>new RazorpayProvider(),'stripe'=>new StripeProvider(),default=>new ManualProvider()};
    }
}
