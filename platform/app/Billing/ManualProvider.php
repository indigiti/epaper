<?php
namespace Digiti\Billing;

final class ManualProvider implements PaymentProviderInterface
{
    public function key(): string{return 'manual';}
    public function createPayment(array $payment,array $context=[]): array{return ['ok'=>true,'provider'=>'manual','status'=>'PENDING_MANUAL','reference'=>$payment['payment_guid']??null];}
    public function verifyWebhook(string $payload,array $headers): bool{return false;}
    public function normalizeWebhook(string $payload,array $headers): array{return [];}
}
