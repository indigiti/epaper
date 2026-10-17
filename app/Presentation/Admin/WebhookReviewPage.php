<?php
declare(strict_types=1);
namespace Digiti\Presentation\Admin;

use Digiti\Core\View;
use Digiti\Infrastructure\Billing\WebhookRepository;

/** Read-only operational queue: never expose raw provider payloads or signing headers. */
final class WebhookReviewPage
{
    public static function show(): never
    {
        $events=(new WebhookRepository())->reviewQueue();
        $html='<section class="card"><h2>Payment webhooks requiring review</h2>'
            .'<p class="muted">These verified provider events were not settled automatically. '
            .'Confirm the provider reference, tenant, amount and invoice before taking any manual action. '
            .'This screen does not mark an invoice paid.</p>';
        $html.='<div class="table-wrap"><table><thead><tr><th>Provider</th><th>Event ID</th>'
            .'<th>Status</th><th>Reason</th><th>Received</th></tr></thead><tbody>';
        foreach($events as $event){
            $html.='<tr><td>'.View::esc($event['provider']).'</td>'
                .'<td><code>'.View::esc($event['provider_event_reference']).'</code></td>'
                .'<td>'.View::esc($event['processing_status']).'</td>'
                .'<td>'.View::esc($event['error_message']).'</td>'
                .'<td>'.View::esc($event['received_at']).'</td></tr>';
        }
        if(!$events)$html.='<tr><td colspan="5">No webhook events currently require review.</td></tr>';
        $html.='</tbody></table></div></section>';
        View::layout('Webhook review',$html,[
            'subtitle'=>'Read-only reconciliation exceptions; limited to the latest 100 events',
            'action'=>'<a class="button" href="/admin/billing">Back to billing</a>'
        ]);
    }
}
