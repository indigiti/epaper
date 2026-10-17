<?php
declare(strict_types=1);
namespace Digiti\Application\Billing;

use Digiti\Billing\ProviderFactory;
use Digiti\Domain\Billing\WebhookEventPolicy;
use Digiti\Infrastructure\Billing\WebhookRepository;

/** Signature-first intake. No settlement is performed in a public HTTP request. */
final class WebhookIngress
{
    public static function receive(string $provider,string $raw,array $headers,?WebhookRepository $repository=null): array
    {
        if(!in_array($provider,['stripe','razorpay'],true))
            throw new WebhookFailure('WEBHOOK_PROVIDER_UNSUPPORTED',404,'Unknown payment provider');
        if($raw===''||strlen($raw)>2097152)
            throw new WebhookFailure('WEBHOOK_PAYLOAD_INVALID',400,'Webhook body is missing or too large');
        $adapter=ProviderFactory::make($provider);
        if(!$adapter->verifyWebhook($raw,$headers))
            throw new WebhookFailure('WEBHOOK_SIGNATURE_INVALID',400,'Webhook signature invalid or expired');
        $event=json_decode($raw,true);
        if(!is_array($event)||json_last_error()!==JSON_ERROR_NONE)
            throw new WebhookFailure('WEBHOOK_PAYLOAD_INVALID',400,'Webhook JSON is invalid');
        $reference=WebhookEventPolicy::eventReference($provider,$event,$headers);
        if($reference===null)
            throw new WebhookFailure('WEBHOOK_EVENT_ID_REQUIRED',400,'Provider event ID is required');
        $duplicate=($repository??new WebhookRepository())->receive($provider,$reference,$raw);
        return ['ok'=>true,'duplicate'=>$duplicate,'queued'=>!$duplicate];
    }
}
