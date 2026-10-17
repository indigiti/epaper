# EP-DI v2.0.0-alpha.2 — Runtime & Publishing Engine

**Status:** Engineering alpha. `v1.1.9.11` remains the immutable recovery baseline until v2 production verification is complete.

This milestone moves the v2 branch from the alpha.1 performance foundation into the runtime/control-plane architecture: compiled Reader Navigation, Redis-first liveness and operational snapshots, transactional cache invalidation, and configurable protected-media offload. It does **not** claim the encrypted Offline Vault or the final Publisher Console/Reader OS visual redesign are complete.

## What changed

### 1. Versioned Reader Navigation runtime

A new `ReaderNavigation` service provides a compiled public navigation data plane and a MariaDB-backed publisher control plane.

Supported scope resolution is most-specific-first with deterministic fallback across:

`Publication + City + Language → less-specific combinations → Global`

The public path reads:

`Redis active snapshot → atomically compiled filesystem JSON → safe built-in default`

Normal public header rendering therefore requires **zero MariaDB reads for navigation**.

The publishing workflow now supports:

- global and scoped Reader Navigation definitions;
- draft revisions;
- header behavior (`inherit`, `editorial`, `compact`, `minimal`);
- sticky behavior;
- Primary, Quick, Trending and Breaking/Live rows;
- safe internal or HTTPS custom destinations;
- desktop/mobile visibility;
- same-tab/new-tab behavior;
- start/expiry schedules;
- item enable/disable, order changes and deletion;
- Preview;
- Publish;
- immutable version history;
- append-only Restore, which creates a new published version rather than rewriting history.

Scheduled items are stored in the immutable compiled snapshot and are activated/expired at read time in PHP. This preserves **zero-DB scheduled navigation** while allowing Breaking/Live and menu items to change automatically at their configured time boundaries.

### 2. Reader Navigation admin control plane

A functional engineering UI is now available under:

`/admin/navigation`

It includes:

- scope list and scoped-navigation creation;
- header behavior controls;
- draft preview;
- Primary / Quick / Breaking / Trending sections;
- item add / toggle / reorder / delete controls;
- explicit Publish action;
- version history and Restore;
- revision/status indicators.

This is the runtime-capable alpha interface. The final high-fidelity Publisher Console/Reader Navigation UX remains scheduled for beta.1.

### 3. Database migration

`database/upgrade-v2.0.0-alpha.2.sql` adds:

- `ep_navigation_scopes`
- `ep_navigation_items`
- `ep_navigation_versions`

The same structures are included in `database/schema.sql` for clean installations.

The migration is additive and is intended to be applied after `upgrade-v2.0.0-alpha.1.sql` on an existing v1.1.9.11-derived database.

### 4. Redis runtime primitives

`RedisStore` now includes SCAN-based namespace operations:

- `scanJson()`
- `deleteByPrefix()`

No blocking Redis `KEYS` operation is introduced.

These primitives support bounded cache invalidation and worker discovery while keeping Redis appropriate for larger keyspaces.

### 5. Transactional cache invalidation

A new `RuntimeCache` service supports cache invalidation tags for hot runtime domains including:

- dashboard/runtime snapshots;
- Reader state;
- settings;
- entitlements;
- navigation.

Invalidation is applied locally and also written as a durable `CACHE_INVALIDATE` event to the transactional outbox. `OutboxPublisher` consumes these events and reapplies the invalidation against shared Redis, allowing runtime cache coherence without turning Redis into the durable event system.

Edition publishing now invalidates relevant dashboard and Reader-state hot caches.

### 6. Redis-first Supervisor worker liveness

Worker health now prefers the expiring Redis heartbeat keys written by the alpha.1 bounded Supervisor workers:

`worker:heartbeat:v2:{lane}:*`

The health path falls back to MariaDB `ep_system_state` only if Redis is unavailable.

This reduces repeated operational reads against MariaDB while keeping a recovery path.

### 7. Publisher runtime snapshot

A new `PublishingRuntime` service creates a short-lived Redis-backed operational snapshot for the dashboard.

On cache miss it collects, in one SQL round trip:

- published editions;
- total pages;
- active processing jobs;
- failed jobs;
- pending outbox events;
- failed outbox events;
- recent error events.

It combines those values with worker health and RabbitMQ queue statistics, then derives an Attention Center model.

The admin dashboard now consumes this operational snapshot and exposes:

- system status;
- publishing KPIs;
- Attention Center;
- Quick Operations;
- recent editions.

The Redis runtime snapshot TTL is intentionally short (about eight seconds with jitter) so the dashboard remains operationally current without recalculating all aggregates on every page view.

### 8. Protected media runtime configuration

The infrastructure dashboard now provides controls for:

- signed media URL token bucket duration;
- optional Nginx `X-Accel-Redirect` internal prefix.

`X-Accel-Redirect` remains **disabled by default**. Do not enable it until the matching private/internal Nginx location has been configured and verified on Cloudways. Enabling an unmatched prefix will break protected media delivery.

### 9. Public navigation integration

The public PHP SSR shell now consumes the compiled Reader Navigation snapshot instead of hard-coded menu links.

The public shell can render:

- Primary navigation;
- Focus/Quick links;
- active Breaking/Live content.

Publication pages pass publication scope. Edition/Reader pages pass publication, city and language scope so the same compiled navigation engine can resolve the correct public header without a database lookup.

### 10. PWA/build coherence

The service worker and runtime are aligned to:

`2.0.0-alpha.2`

The alpha.1 PWA architecture remains intact. The legacy plaintext Reader-media compatibility cache is still deliberately present until beta.2 replaces it with the encrypted Offline Vault.

## Expected runtime impact

These are architecture/query-path expectations from the code, **not production latency measurements**.

| Surface | v2.0.0-alpha.2 behavior |
|---|---|
| Public navigation | Redis/file/default; normally 0 MariaDB queries |
| Scheduled nav activation/expiry | PHP read-time filtering; 0 MariaDB queries |
| Worker health | Redis heartbeat scan; MariaDB fallback only |
| Admin operational dashboard | short Redis snapshot; one aggregate SQL round trip on miss |
| Cache invalidation | tagged Redis invalidation propagated through durable outbox |
| Protected media | PHP authorization with optional Nginx byte offload |

## Deployment sequence

For an existing alpha.1 installation:

1. Back up application files and database.
2. Keep the verified `v1.1.9.11` recovery package available.
3. Deploy the alpha.2 files.
4. Apply once:
   `database/upgrade-v2.0.0-alpha.2.sql`
5. Verify Redis connectivity.
6. Restart the Supervisor-managed worker groups so all workers run the alpha.2 runtime.
7. Run:
   `php bin/release-check.php`
8. Open `/admin/navigation`, create/verify the Global scope and publish a navigation version.
9. Verify public desktop/mobile headers and scoped fallback.
10. Leave `media.x_accel_prefix` empty unless Nginx has already been configured for the exact internal media path.

For a clean installation, `database/schema.sql` already contains the alpha.2 navigation schema.

## Release checks completed

- PHP syntax: **86 runtime/project PHP files checked — PASS**.
- JavaScript syntax: app, CRUD, Reader, Reader Prefetch, Guest Clipping and Service Worker — **PASS**.
- v2.0.0-alpha.2 build guard — **PASS**.
- Tenant SQL guard — **PASS**; 156 application SQL statements touching tenant-owned tables verified to carry `tenant_guid` (with documented platform-wide worker CLI exceptions).
- Reader parity guard — **PASS**.
- Cumulative `php bin/release-check.php` — **PASS**.

These are static/repository release gates. Production-sized `EXPLAIN`/`EXPLAIN ANALYZE`, real Redis hit rate, RabbitMQ throughput, Cloudways Nginx media offload and Core Web Vitals still require testing on the target environment before production lock.

## Deliberately not complete in alpha.2

- Encrypted PWA Offline Vault and rolling seven-day encrypted edition packs.
- Device-bound offline key wrapping/license expiry/revocation.
- Removal of the legacy plaintext Reader-media compatibility cache.
- Final Publisher Console visual redesign.
- Final Reader OS / Library visual redesign.
- Topic Library semantic entity system and semantic article/topic/author publishing.
- News/AI SEO production layer, news sitemap and Editorial Trust Center.
- Production Nginx `X-Accel-Redirect` server configuration.
- Production-sized query-plan and load test evidence.

## Next milestone

**v2.0.0-beta.1 — Publisher Console & Reader Navigation Experience**

Primary target:

- apply the approved premium operational-console design system;
- complete high-fidelity responsive Reader Navigation builder UX;
- add typed plan/entitlement builder UX;
- strengthen dashboard/processing/error-center interaction design;
- preserve all alpha.2 runtime/cache/query budgets.

The encrypted Offline Vault remains the following beta.2 milestone so its cryptographic/storage lifecycle can be implemented and tested independently from the console redesign.
