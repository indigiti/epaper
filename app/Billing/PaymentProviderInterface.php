<?php
namespace Digiti\Billing;

interface PaymentProviderInterface
{
    public function key(): string;
    public function createPayment(array $payment,array $context=[]): array;
    public function verifyWebhook(string $payload,array $headers): bool;
    public function normalizeWebhook(string $payload,array $headers): array;
}
