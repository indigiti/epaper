# EP-DI v2.0.0-beta.2.1 Build Manifest

- Version: `2.0.0-beta.2.1`
- Base / required version: `2.0.0-beta.2`
- Release type: cumulative beta performance/runtime release
- Feature: Multi-Layer Cache & Delivery Engine
- Database migration: **none**
- Upgrade Center: no schema action required
- Worker restart: **recommended / required for long-running Supervisor workers** so `OutboxPublisher` and cache-generation runtime changes are loaded
- Service-worker cache: `epdi-v2.0.0-beta.2.1`
- Offline model: beta.2 encrypted IndexedDB Offline Vault preserved unchanged

## New runtime files

- `app/Services/MultiLayerCache.php`
- `app/Services/EditionManifest.php`
- `app/Services/PublicReadCache.php`
- `app/Services/CacheInspector.php`
- `bin/cache-maintenance.php`
- `tests/build-guard-v2.0.0-beta.2.1.php`

## Updated runtime files

- `app/App.php`
- `app/Core/Http.php`
- `app/Core/RedisStore.php`
- `app/Core/View.php`
- `app/Services/Media.php`
- `app/Services/OutboxPublisher.php`
- `app/Services/ReaderNavigation.php`
- `app/Services/RuntimeCache.php`
- `app/Services/TenantData.php`
- `bin/scheduler.php`
- `bin/release-check.php`
- `public_html/.htaccess`
- `public_html/offline.html`
- `public_html/offline-library.html`
- `public_html/offline-reader.html`
- `public_html/service-worker.js`
- `VERSION`

## Cache stack

1. request-local PHP memory;
2. Redis shared read/runtime cache;
3. atomic filesystem fallback / compiled read models;
4. MariaDB canonical source of truth;
5. HTTP/browser/CDN caching for safe immutable assets/media;
6. Service Worker application shell only;
7. encrypted IndexedDB Offline Vault for protected offline editions.

## Functional guarantees

- published-edition metadata can resolve through immutable revisioned manifests before MariaDB;
- published public read models use generation-aware multi-layer caching;
- cache expiry supports fresh + stale windows and token-owned one-owner rebuild protection;
- settings/master/public/highlight/navigation invalidation is revision based rather than TTL-only;
- publish/update flows can queue asynchronous cache warming;
- signed immutable media remains browser/proxy cacheable for its token window;
- protected `/media/*` and `/api/v2/offline/*` remain excluded from Service Worker persistent Cache Storage;
- versioned static assets are long-cache immutable while `service-worker.js` is explicitly revalidated;
- no beta.2 Offline Vault database/security model is weakened;
- no database migration is introduced by beta.2.1.

## Release gates

- beta.2.1 build guard
- Offline Vault static/security guard
- Supervisor runtime guard
- Schema Doctor guard
- Editions Operations guard
- Processing Control guard
- Audit UUID safety guard
- tenant SQL isolation guard
- Reader parity guard
- cumulative v2 guard
- PHP / JavaScript syntax validation
- package overlay verification against exact beta.2 release
- ZIP integrity / SHA-256 verification
