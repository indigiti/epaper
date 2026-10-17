# Signed Platform → ePaper cache invalidation — Phase 7 (17 October 2026)

**Status:** Draft PR #23, unmerged and not deployed. Cloudways and DigiOps production were not modified. All tests use source fixtures/disposable MariaDB, not live merchant/customer data.

## What is built

### Delivery architecture

`Platform Admin POST → Application mutation → MariaDB transaction → outbox_events(cache.invalidate)
→ Platform bin/worker.php → HTTPS/HMAC-SHA256 → ePaper /internal/v1/platform/cache-invalidate
→ durable receipt + Redis/file eviction → signed-event ACK → outbox PROCESSED`.

The receiver route is **before** ePaper's `DomainResolver::resolve()`, so a suspended domain cannot prevent its own invalidation. Every event has a UUIDv7 `event_id`, a tenant UUID, a bounded list of hostnames and `kind=control_plane.changed`. Host lists are derived from authoritative Platform domain relationships, not user-posted destinations; one event carries up to 32 hostnames.

HMAC is computed over `<unix_timestamp> + newline + <raw JSON body>`. Headers:
`X-Digiti-Timestamp` and `X-Digiti-Signature: v1=<lowercase hex SHA256>`.
ePaper requires 32+ character private shared key, ±300-second timestamp window, valid payload and UUIDs, then stores an event-specific SHA256 digest in `ep_system_state`. Duplicate signed delivery is idempotently acknowledged; same event ID with different payload is rejected. Only `HTTP 200 {ok:true,event_id:<same event id>}` permits the Platform worker to acknowledge its outbox record.

Redis keys evicted: `domain:v2:<host>`, `tenant-meta:v2:<tenant>`, `access:v2:<tenant>`, `tenant:<tenant>:entitlements`, `tenant-synced:<tenant>`. Disk cache: `storage/cache/control-plane/<sha256(key)>.json` for domain and tenant metadata. Redis outages while configured fail the receiver with HTTP 503 and cause the worker to retry; no success is acknowledged without the cache invalidation attempt.

Outbox retries use the existing worker lease/retry/dead-letter rules (15-minute stale processing lease and a maximum of five attempts). The event+outbox insert is part of a transaction for tenant, subscription, domain and registry mutations. No HTTP call is made in the Admin request transaction.

### Admin extraction

Dedicated `Application/Admin/RegistryMutations.php` handles edit/status/remove of organisations, users, products, applications, service accounts, features, plans and prices. `RegistryCreates.php` handles registration of organisations, users, products, applications, tenants, domains and service accounts. `ManageDomain`, `RemoveDomain`, `ManageTenant`, `ManageSubscription`, `VoidInvoice`, `ManageMfa` and `IssueImpersonation` own the other Admin mutation use cases. All existing Admin route URLs and forms remain.

**Important scope:** write-side mutation orchestration is extracted, but a number of Admin read queries and HTML forms still live in `App.php` and `Admin/Crud.php`. They are presentation/read-model cleanup tasks, not yet a complete controller/view decomposition. Do not mark the entire Admin frontend architecture as fully migrated.

## Configuration — isolated staging first

Only private operator-maintained runtime configuration should hold these keys. Do **not** commit keys or backups.

In Platform's private `config/config.php`:
- `integrations.epaper_invalidation_url`: exact HTTPS origin plus `/internal/v1/platform/cache-invalidate`. A fixed operator-configured URL only; redirects are disabled, TLS certificates/hostname are verified.
- `integrations.epaper_invalidation_secret`: a random unique 32+ character HMAC secret.

In ePaper's private `config/config.php`:
- `platform.invalidation_secret`: exactly the same HMAC secret.

Keep this key **different** from OAuth/client/service tokens, `app.key`, billing keys and Cloudways credentials. Restrict access to the webhook at WAF/network level where possible without blocking the server-to-server POST. Provision key rotation overlap and multi-app routing **before** enabling multiple distinct ePaper origins; the current sender supports one configured receiver URL for the shared ePaper application, not arbitrary per-tenant receiver URLs.

Run Platform worker and scheduler under supervisor in isolated staging; confirm outbound HTTPS from Platform and inbound signed POST through any Cloudways/ModSecurity/WAF controls. Do not disable WAF globally to make tests pass.

## Staging benchmark and acceptance

With **Platform `app.env=staging`** and a dedicated staging-only ePaper tenant:

```bash
EPDI_STAGING_INVALIDATION=allow php platform/bin/staging-invalidation-probe.php --tenant=<STAGING_TENANT_UUID> --samples=10 --timeout=30
```

The command enqueues durable events, observes `outbox_events` until authenticated acknowledgement, and prints per-attempt elapsed milliseconds and p95. It **does not** measure browser/CDN propagation; report those separately. `/admin/operations` now exposes pending/failed/acknowledged counts and p95 of the last 100 completed delivery events.

Acceptance cases:
1. Two independent tenants, two hostnames, shared product, distinct subscription status. Suspend tenant A; only its host/entitlement/access keys are invalidated. Tenant B remains accessible.
2. Resume/expire/cancel subscription and remove/revalidate a domain; confirm the corresponding outbox event and signed ePaper response.
3. Replay the identical signed event: second response is `duplicate:true`, no mutation; change raw body, signature or timestamp: rejected. A missing signing key fails closed.
4. Stop ePaper; confirm outbox remains unacknowledged and retries, including after 5-minute backoff. Stop Redis on ePaper; ensure HTTP 503 and no processed state until Redis is available again. Restore and verify eventual acknowledgement.
5. Simultaneous Admin edits, removed domains, worker revalidation and overlapping deliveries: removed domains never become ACTIVE.
6. Measure origin path p50/p95/p99 and ePaper `access:v2` cache age. Separately verify Cloudflare/CDN browser/service-worker cache expiry, signed-media token expiry and existing reader sessions.
7. Attempt to invoke receiver via a suspended hostname and confirm it can still authenticate and invalidate before domain resolution.
8. Count deferred outbox/failed rows, verify audit events and inspect logs without leaking HMAC headers or tokens.

## Important limitations and blockers

- This implementation is **authenticated origin-side cache invalidation**, not a Cloudflare CDN purge and not forced end-user session revocation. Those are separate product/edge integration gates.
- The receiver dedup ledger in `ep_system_state` grows with deliveries; design an approved retention horizon and enforce event-ID dedup retention before purging. Do not clear IDs prematurely.
- The sender is a **single ePaper receiver** configured by the operator. If customer installations use different ePaper servers/origins, a registry of trusted per-app webhook destinations, signatures/keys and its own routing tests are required before production rollout.
- Previous public repository history exposed runtime secrets. Key rotation/history remediation, private config cutover and separate Platform database backup + isolated restore are required. Never deploy this branch by direct Git pull because the draft removes formerly tracked runtime settings.
- Source/CI green does not prove a live Cloudways run, WAF acceptance, database failover, actual latency or production readiness. Production release, billing merchant sandbox and DigiOps two-app rollback gates remain open (#2, #21, #22).
