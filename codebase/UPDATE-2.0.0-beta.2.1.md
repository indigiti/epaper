# EP-DI v2.0.0-beta.2.1 — Multi-Layer Cache & Delivery Engine

## Purpose

Beta.2.1 is a focused read-delivery/performance release on top of the encrypted beta.2 Reader OS + Offline Vault. It formalizes the multi-layer cache architecture used by public/Reader delivery, reduces avoidable MariaDB reads, adds safe stale-while-revalidate behavior and makes cache health observable from the Publisher Console.

The encrypted Offline Vault is intentionally unchanged: protected newspaper pages are still stored offline as ciphertext in IndexedDB and are never opportunistically persisted in Service Worker Cache Storage.

## Server cache architecture

```text
Request
  ↓
L0 PHP request memory
  ↓ miss
L1 Redis shared cache
  ↓ miss
L2 atomic filesystem / compiled snapshot
  ↓ miss
MariaDB source of truth
  ↓
repopulate upper layers
```

`MultiLayerCache` adds fresh/stale windows, TTL jitter, token-owned Redis rebuild locks with filesystem fallback and atomic L2 writes. Lock release uses owner-token comparison so one request cannot release another request's lock. When Redis is healthy and a rebuild lock is already owned elsewhere, the contender waits/rechecks L1/L2 instead of immediately taking a filesystem lock and duplicating the MariaDB rebuild. A stale value can be returned immediately while one request schedules refresh, preventing a popular cold key from creating a database stampede.

## Edition Manifest v3

Published editions now support immutable revisioned manifest snapshots. A manifest contains stable edition/page metadata used by the Reader and is addressed through a current revision pointer. Lookup order is Redis revision pointer/value → compiled filesystem manifest → MariaDB rebuild.

The filesystem representation is atomically replaced and old revisions can be retained/cleaned separately from ordinary application read cache.

## Revision-based invalidation

Runtime generations now cover settings, Reader Navigation, public editions, master data, highlights and SEO state. Administrative changes bump the relevant generation instead of depending only on expiry. Old generation entries become unreachable immediately and are removed later by maintenance.

## Publish-time warming

Publication/update flows can enqueue a durable `CACHE_WARM` transactional-outbox event. The outbox worker warms the published edition manifest and common public read models after invalidation, so the first reader after publication is less likely to pay a MariaDB rebuild cost.

## Public read models

Revisioned cache wrappers now cover high-frequency public data such as:

- editions with cover metadata;
- active publication/master data;
- archive filter reference data;
- highlights;
- short-lived public search results.

The server continues to render semantic SSR HTML, so this optimization does not turn public pages into a client-only SPA.

## Media delivery

Media asset metadata is cached through the multi-layer cache. Valid signed immutable media responses carry public/immutable browser/proxy cache headers for their remaining token lifetime while the beta.2 Service Worker continues to request `/media/*` and Offline Vault API responses with `cache: no-store`.

This distinction is intentional: HTTP/browser/CDN reuse of an authorized immutable signed URL is allowed; generic Service Worker persistence of protected newspaper plaintext is not.

## Static delivery

Versioned `/assets/*` responses use a long immutable cache policy. `service-worker.js` is explicitly revalidated/no-cache so a new release activates promptly instead of being hidden behind an old worker script.

## Publisher Console — Performance & Cache

`/admin/performance` now exposes:

- Redis connected state, memory use, key count and server hit ratio;
- current-request cache counters;
- runtime filesystem cache file count/size;
- compiled edition manifest revision/pointer count and size;
- current generation counters;
- queue/cache operational context.

Infrastructure administrators also receive scoped operations:

- Warm cache;
- Cleanup expired/obsolete cache;
- Purge application read cache;
- Rebuild published manifests.

These actions are deliberately separated instead of providing one destructive "clear everything" operation.

## Maintenance CLI

`bin/cache-maintenance.php` cleans expired runtime-cache files and old manifest revisions per active tenant. `--warm` additionally warms public read models and recent published manifests.

Suggested Cloudways cron (adjust path):

```bash
php /home/master/applications/<APP_ID>/public_html/../bin/cache-maintenance.php
```

A 6-hour or daily cleanup cadence is sufficient for most installations. `--warm` is optional and is normally better used after deployment/publishing diagnostics rather than on every cron run.

## Database

**No beta.2.1 database migration exists or is required.**

The existing beta.2 Offline Vault schema remains the required database baseline. `/admin/system/upgrade` should report the database aligned after deployment.

## Deployment from beta.2

1. Take a Cloudways application/database backup.
2. Extract the beta.2.1 hotfix over the existing beta.2 application root.
3. Restart the EP-DI Supervisor workers so long-running processes load the new Outbox/cache runtime code.
4. Hard refresh once so `epdi-v2.0.0-beta.2.1` Service Worker assets activate.
5. Verify `/admin/performance`, `/admin/system/health`, `/`, `/archive`, one published Reader edition, Reader OS and an already-downloaded encrypted Offline Vault edition.
6. Optionally run `php bin/cache-maintenance.php --warm` once after deployment.

## Verification expectations

After warm traffic, `/admin/performance` should show Redis/manifest hits and public pages should require substantially fewer repeated MariaDB reads. Exact hit-rate/query targets must be measured on production-sized data; beta.2.1 does not claim a synthetic percentage without live load testing.

## Regression invariants

- beta.1.1 scheduling/reprocess behavior unchanged;
- beta.1.2 processing/recovery behavior unchanged;
- beta.2 Reader OS/Library/device licensing unchanged;
- Offline Vault ciphertext/crypto model unchanged;
- RabbitMQ/Supervisor topology unchanged;
- MariaDB remains canonical source of truth.
