# Digiti Platform — billing and recovery multilayer audit (2026-10-17)

## Implementation in draft PR #23

| Layer | Responsibility | Files |
|---|---|---|
| Presentation | Signed webhook endpoint; read-only exception page | `app/App.php`, `app/Presentation/Admin/WebhookReviewPage.php` |
| Application | Signature-first receipt, safe settlement, invoice create, lease recovery | `app/Application/Billing/*`, `app/Application/Operations/QueueLeaseRecovery.php` |
| Domain | Provider event shape; exact INR amount parsing | `app/Domain/Billing/*` |
| Infrastructure | Atomic receipt/outbox, invoice and financial record persistence | `app/Infrastructure/Billing/*` |
| Worker/scheduler | Outbox handler, 15-minute lease, retry/dead-letter, DB advisory-lock fallback | `bin/worker.php`, `bin/scheduler.php` |

## Financial invariants

- Webhooks are accepted only from known Stripe/Razorpay providers, verified against raw body signatures, limited to 2 MiB, and require a provider event ID.
- Stripe signatures must be no older than five minutes (±300 seconds); Razorpay uses HMAC over the raw body and an event ID header.
- Event receipt and enqueue occur in the **same MariaDB transaction**. The provider/reference uniqueness constraint prevents duplicated outbox events. Reuse of an existing event ID with different raw contents returns a conflict.
- A successful event is eligible for automated reconciliation only when an existing payment has an **unambiguous provider reference**, compatible status, exact amount and currency. Its linked invoice (if any) must match tenant, amount and currency.
- The payment/invoice changes and outbox acknowledgement share one database transaction. An already-paid invoice with a different pending payment requires human review, not automatic duplicate settlement.
- Unknown or unsupported provider event types, missing payments, invalid amounts/currencies and ambiguous matches become `webhook_events.processing_status=REVIEW_REQUIRED`, with a preserved reason. They are *not* represented as successful payments.
- Admin review page `/admin/billing/webhooks` shows only safe summary data. It never renders signed raw payloads and it cannot mark invoices paid.
- Manual invoice amounts are parsed into integer INR paise without float conversion; header and line are committed together for an active tenant.
- Worker crashes no longer imply success. After the 15-minute processing lease the scheduler requeues or fails stale outbox work according to attempts. Redis outages use a MariaDB advisory lock for scheduler coordination.

## What is deliberately not claimed

- Live provider payment order/PaymentIntent **creation** is not implemented in this package. Provider adapters explicitly report that a merchant-specific network action is required. For automatic settlement, a correctly linked payment record and provider reference must already exist.
- Refund settlement, disputes, partial capture, chargebacks, provider reconciliation queries, invoice partial payments, and automated manual exception approval are not implemented. Unsupported events are retained for review.
- Existing `payment.reconcile` background job still does not perform a real provider fetch; it must not be described as financial reconciliation.
- Legacy Admin CRUD and remaining large Platform App methods remain mixed. This is another incremental slice rather than a full conversion.
- ePaper domain/entitlement caches require separate revocation propagation and tenant tests.
- The schema/fixtures and source tests do not certify production payment behavior.

## Verification and next staging gates

Dedicated CI checks run on Platform PHP 8.3. The `billing-mariadb` job starts a fresh **disposable MariaDB 10.11**, constructs fixture-only private settings, applies actual `platform/database/schema.sql`, and executes `platform/tests/billing-mariadb-integration.php`.

Before real financial traffic:
1. Verify **separate Platform backup and isolated restore**, private config cutover and credential/key rotation. The prior accidental committed live-looking values remain compromised until revoked.
2. Run real merchant sandbox checkout with configured provider credentials, two customers and two applications, webhook retries and real signed receipt headers.
3. Test same-event concurrent delivery, worker crashes, recovering leases, refunds/disputes and fake-signature denial at the actual Cloudways/WAF layer.
4. Add strongly scoped payment creation, provider-side refunds, payment reconciliation API, customer invoice communications and audit trails.
5. Complete DigiOps atomic deployment and rollback testing with independent Platform/ePaper code and persistent data; refresh artifact-specific checksums.

**Deployment:** Draft PR #23 is not merge/deploy-approved. Untracking previously committed `platform/config/config.php` can delete the live file on a direct Git checkout. Public Git history still holds exposed secrets until operator-led rotation and cleanup. See Issues #2, #21, #22.
