<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Billing;

use Digiti\Core\{Store,Uuid};
use Digiti\Application\Billing\WebhookFailure;

/** Atomic event receipt/outbox insertion, protected by provider event ID uniqueness. */
final class WebhookRepository
{
    public function receive(string $provider,string $reference,string $raw): bool
    {
        return Store::tx(static function()use($provider,$reference,$raw):bool{
            $guid=Uuid::v7();
            $inserted=Store::exec(
                'INSERT INTO webhook_events(webhook_event_guid,provider,provider_event_reference,verification_status,processing_status,headers_json,payload,received_at)
                 VALUES(?,?,?,"VERIFIED","RECEIVED",?, ?,NOW(6))
                 ON DUPLICATE KEY UPDATE provider_event_reference=provider_event_reference',
                [Uuid::toBinary($guid),$provider,$reference,'{}',$raw]
            );
            if($inserted===0){
                $prior=Store::one('SELECT payload,verification_status FROM webhook_events WHERE provider=? AND provider_event_reference=?',[$provider,$reference]);
                if(!$prior||$prior['verification_status']!=='VERIFIED'||!hash_equals(hash('sha256',(string)$prior['payload']),hash('sha256',$raw)))
                    throw new WebhookFailure('WEBHOOK_EVENT_CONFLICT',409,'Event reference already recorded with different contents');
                return true;
            }
            if($inserted!==1)throw new \RuntimeException('Unexpected webhook receipt result');
            Store::exec(
                'INSERT INTO outbox_events(outbox_event_guid,event_type,aggregate_type,aggregate_guid,payload,status,available_at,created_at)
                 VALUES(?,"payment.webhook.received","webhook_event",? ,?,"PENDING",NOW(6),NOW(6))',
                [Uuid::toBinary(Uuid::v7()),Uuid::toBinary($guid),json_encode(['webhook_event_guid'=>$guid,'provider'=>$provider],JSON_THROW_ON_ERROR)]
            );
            return false;
        });
    }
}
