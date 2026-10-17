# Build Manifest — ePaper SaaS v1.0.1.0

Release state: **TESTED**

## Scope

Processing-engine upgrade only. Adds RabbitMQ transport, Supervisor workers, transactional outbox publishing, stage parallelism, retries/dead-letter handling, queue health and Processing Center queue visibility. Existing Reader/media/deep-zoom/OCR/clipping/auth/domain/tenant behavior is preserved.

## Database

No schema migration. Reuses `ep_processing_jobs`, `ep_processing_steps`, `ep_outbox_events`, `ep_system_state`.

## Protected behavior

- `/media/{token}` retained
- `/edition/{slug}/page/{page}` retained
- Deep Zoom tile API retained
- clipping API retained
- account clip/library routes retained
- Reader seamless navigation/zoom/fit/clip/Smart Article/PWA guards retained
- analytics event catalogue retained
- tenant query guard retained

## New operational files

`app/Services/RabbitMq.php`, `QueueService.php`, `OutboxPublisher.php`, `ProcessingPipeline.php`, retry/permanent job exceptions, Rabbit workers/outbox publisher/configurator/diagnostic, Supervisor template and Rabbit setup documentation.

## Runtime verification still required

The packaging environment has no live Cloudways RabbitMQ broker or Supervisor service, so broker authentication, live consumer counts, redelivery, retry TTL behavior and real multi-worker PDF processing must be verified on Cloudways before release state can become VERIFIED.
