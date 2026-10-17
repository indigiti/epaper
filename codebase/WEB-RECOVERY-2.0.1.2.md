# EP-DI v2.0.1.2 — Web Recovery Center

## Security boundary

Path: `/admin/system/recovery`
Access: canonical hostname + existing admin session + infrastructure role (`OWNER` or `ADMIN`).
Mutations: POST only, CSRF protected, rate-limited, audited.

## Queue model

`QUEUE_PUBLISH` statuses mean:

- `PENDING` — waiting for Outbox Publisher.
- `PROCESSING` — currently being published.
- `PROCESSED` — RabbitMQ publish was confirmed; this is a successful dispatch receipt.
- `FAILED` — transport attempt failed or the client could not prove success.

A DB job that is still `QUEUED` but whose latest queue-publish receipt is `PROCESSED` is classified **Dispatch confirmed**. It is not automatically re-published.

## Safe failed-outbox classification

- Historical probe → close.
- Orphan job → close.
- Terminal job → close.
- Later PENDING/PROCESSING/PROCESSED dispatch exists → close superseded failure.
- Known php-amqplib unknown-delivery-tag confirm failure → recover to PENDING.
- Invalid payload → leave for review.
- Unknown active transport failure → leave for review.

## Missing dispatch repair

The separate repair action targets only active QUEUED/RETRY_WAIT jobs that have **no QUEUE_PUBLISH history at all**. `ensureDispatched()` rechecks current transport state before creating an event, preventing a stale analysis screen from forcing a duplicate.

## Scheduler Doctor

Web and cron share `PublishingScheduler::runOnce()`. MariaDB `GET_LOCK("epdi_scheduler_v2",0)` prevents overlapping runs. `scheduler.last_run` records any source; `scheduler.last_cron` records only the recurring CLI cron; the last ten run records are stored in `scheduler.history`.

## Redis telemetry

The page shows Redis server memory, maxmemory percentage/policy, selected DB key count, EP-DI namespace key count, cumulative historical evictions and delta since the operator-captured baseline. No server-wide destructive Redis operation is exposed.
