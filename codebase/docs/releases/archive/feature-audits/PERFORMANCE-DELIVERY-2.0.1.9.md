# EP-DI v2.0.1.9 — Performance & Delivery Engine

This release removes avoidable work from the reader hot path without changing EP-DI's tenant, reader identity, publishing, recovery or offline-vault architecture.

## Request pipeline

- Anonymous public GET pages no longer create a PHP session merely to render `window.EpaperSaas`.
- CSRF state is issued lazily through `/api/v1/session/csrf` when a public interaction first needs a state-changing API request.
- Existing authenticated public GETs release the PHP session lock after identity/access validation, allowing media/API requests to proceed concurrently.
- Signed `/media/` requests are dispatched before reader-session/account revalidation. The media token remains tenant-bound and HMAC protected.

## Platform control-plane cache

- Domain resolution is cached in Redis and a bounded filesystem control-plane fallback.
- Tenant metadata is cached independently.
- A local tenant reconciliation marker avoids selecting/updating `ep_tenants` on every cached request.
- `ep_tenants.updated_at` is changed only when authoritative tenant metadata actually changes.

Default TTLs:

- domain: 600 seconds
- tenant metadata: 900 seconds
- local tenant reconciliation: 600 seconds

## Protected media

- X-Accel remains the recommended production mode.
- Media responses expose `X-EPDI-Media-Delivery: x-accel|php` for diagnostics.
- Performance telemetry records the delivery mode without storing media paths or reader identity.

## Database query shape

- Subscribers trusted-device/session counts are aggregated once and joined, replacing three correlated subqueries per subscriber row.
- Edition processing summaries use one grouped processing aggregate for update time, active count, render count and latest error instead of repeated correlated queries.

## Performance Center

`/admin/performance` now includes a bounded rolling Redis window:

- request p50 / p95 / p99
- average DB queries and DB milliseconds per request
- route-class p95 and query budget
- X-Accel vs PHP media-delivery samples
- Core Web Vitals p75: LCP, INP and CLS

Request samples store only route class, method, status, timing, query counters, Redis counters, session-open state and media-delivery mode. No SQL text, URL query strings, request bodies, cookies, phone/email or reader identifiers are stored.

Browser Web Vitals are sampled at 10% by default and contain only pathname route + metric values.

## Cache correctness

The Service Worker shell now requests all versioned assets with the current `2.0.1.9` cache-busting version rather than the historical `2.0.1.4` query string.

## Deployment

No database migration is required. Upload the release/patch, restart PHP-FPM if desired, and restart long-running workers so they load the current code. If X-Accel is configured, verify the Nginx internal location still maps to EP-DI private storage.
