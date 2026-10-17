# EP-DI v2.0.0-alpha.1 — Publisher OS Performance Foundation

**Status:** Engineering alpha. Do not replace the verified v1.1.9.11 recovery package yet.

This milestone starts the v2 architecture lock: fewer MariaDB round trips, Redis-first repeated reads and worker liveness, bounded Supervisor workers, a lean PWA shell, Reader tile/prefetch optimization, and semantic SEO hooks. It intentionally does **not** claim the encrypted Offline Vault, new Publisher Console, or semantic article publishing layer are finished.

## What changed

### Query and cache layer

- Home, Publication and Archive cover rendering now use one joined edition/first-page-assets query rather than querying page + assets per edition.
- Reader initial boot and offline preparation use `pagesWithAssets()` rather than one asset query per page.
- Reader state uses `pageWithAssets()` and a `reader:state:v2:*` Redis namespace.
- Reader progress uses a single MariaDB upsert and writes a short-lived Redis resume snapshot.
- Reader OS / Library thumbnails are joined into their list queries rather than calling `pageVisual()` for every card.
- Dashboard and account overview aggregates use a single SQL round trip on cache miss plus a 10-second Redis snapshot.
- Tenant settings are loaded by scope in one SQL query and cached both request-locally and in Redis for about one hour with TTL jitter.
- Archive year filters now use date ranges so the edition date index remains usable.

### Redis / Supervisor / RabbitMQ

- Rabbit workers and the outbox publisher write 90-second Redis liveness keys.
- MariaDB heartbeat persistence is throttled to once per 15 seconds instead of every worker touch.
- Rabbit workers recycle after a configurable bounded job count (`queue.worker_max_jobs`, default 250); Supervisor restarts them.
- Supervisor example adds startup retry guardrails.
- Redis telemetry now includes reads, hits, misses, writes, errors and hit-rate.

### Reader performance

- Reader navigation consumes prefetched state instead of automatically fetching the same state again.
- High-resolution Deep Zoom now mounts only tiles intersecting the visible viewport plus one-tile overscan instead of the entire top-level tile grid.
- Pointer/pinch rendering is coalesced with `requestAnimationFrame()`.
- Processing Center polling is adaptive: ~2 seconds while jobs are active, ~10 seconds while idle, ~15 seconds after errors, and the table is rebuilt only when its fingerprint changes.
- Command-palette pointer movement now updates active state without rebuilding all command results.

### Media delivery foundation

- Signed media URLs are stable within a configurable time bucket (default 5 minutes), improving cache reuse without removing expiry.
- Optional `media.x_accel_prefix` enables Nginx `X-Accel-Redirect` file delivery. It is disabled by default and must only be enabled after the matching internal Nginx location exists.

### PWA foundation

- Service worker and application assets are aligned to the same v2 build.
- Install-time shell is deliberately small; Reader/Admin/Auth assets are cached after use rather than preloaded for everyone.
- Public document navigation uses network-first with a bounded public page cache and an offline fallback.
- `/admin`, `/account`, login/auth and `private`/`no-store` HTML are never persisted by the navigation cache.
- PWA manifest includes shortcuts for Today, Archive, Search and Offline Library.
- A static offline fallback is included.

**Important:** the existing plaintext Reader-media compatibility cache remains temporarily available because v1 offline behavior depends on it. It is explicitly marked legacy and is scheduled to be removed when the encrypted Offline Vault lands. Do not describe alpha.1 as encrypted offline access.

### SEO / AI-discovery foundation

`View::layout()` now accepts:

- `description`
- `lang`
- `structured_data`

`structured_data` is safely emitted as server-rendered `application/ld+json`. This is the rendering foundation for future Organization, NewsArticle, Person/author, Topic and trust-center entities. No synthetic E-E-A-T score or invented authority markup has been added.

## Expected query impact

These are architectural/query-count estimates from the code paths, **not production latency measurements**.

| Surface | Previous pattern | v2 alpha.1 pattern |
|---|---|---|
| Home covers | edition query + ~2 queries per cover | one edition/cover join |
| Archive (up to 200) | potentially ~400 page/asset queries after edition list | one edition/cover join |
| Reader 40 pages | pages query + ~40 page-asset queries | one pages/assets join |
| Reader state | page + assets queries | one joined query, then Redis hit |
| Offline prepare | pages + N asset lookups | one pages/assets join |
| Reader progress | select + update/insert | one upsert |
| Layout settings | many individual setting queries | max one query per active scope on miss; Redis thereafter |
| Account Library thumbnails | one `pageVisual()` lookup per item | thumbnail joined into list query |
| Admin dashboard metrics | four independent SQL calls | one SQL round trip on cache miss + Redis snapshot |

## Database upgrade

New installations receive:

```sql
KEY ix_setting_scope (tenant_guid,scope_type,scope_guid,setting_key)
```

Existing v1.1.9.11 databases should apply once:

`database/upgrade-v2.0.0-alpha.1.sql`

Do not run an `ALTER TABLE` blindly twice. Verify the index does not already exist before repeating an interrupted/manual deployment.

## Configuration additions

```php
'media' => [
    'token_bucket_seconds' => 300,
    'x_accel_prefix' => '',
],

'queue' => [
    'worker_max_jobs' => 250,
    // existing queue configuration...
],
```

## Release checks completed

- PHP syntax: 81 PHP files checked — PASS.
- JavaScript syntax: app, CRUD, Reader, Reader Prefetch, Guest Clipping and Service Worker — PASS.
- v2.0.0-alpha.1 build guard — PASS.
- Tenant SQL guard — PASS (207 tenant-owned SQL statements checked).
- Reader parity guard — PASS.

The historical `processing-progress-guard.php` is version-pinned to an old 1.0.0.5 architecture and is not a valid v2 release gate; its useful assertions are being superseded by the new v2 build guard.

## Not yet implemented

- Encrypted Offline Vault / rolling seven-day encrypted editions.
- Device-bound offline license/key wrapping and expiry purge.
- Full Publisher Console visual redesign from the approved inspiration.
- Reader OS / Library visual redesign.
- Scoped Reader Navigation compiler (Global → Publication → City → Language), Preview/Publish/version rollback.
- Semantic article extraction/editorial approval workflow.
- Author/Topic/Entity pages and Editorial Trust Center.
- NewsArticle/Organization/Person production schemas and sitemap/news-sitemap generators.
- Multilingual hreflang publishing workflow.
- Optional external OCR/article search engine.
- Production Nginx `X-Accel-Redirect` configuration.

## Next milestone

**v2.0.0-alpha.2 — Runtime & Publishing Engine** should focus on Redis-backed compiled Reader Navigation, cache invalidation/events, worker/queue observability, dashboard operational snapshots, direct media delivery configuration, and the database/query performance audit against a production-sized data copy.

After that:

- **beta.1:** Publisher Console + Reader Navigation UX.
- **beta.2:** Reader OS + encrypted Offline Vault/PWA.
- **rc.1:** semantic SEO / News SEO / AI-discovery / E-E-A-T trust infrastructure.
- **rc.2:** security, accessibility, Core Web Vitals and device/browser hardening.
