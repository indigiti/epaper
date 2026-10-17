## 2.0.1.2 — Web Recovery Center & Readiness Accuracy

- Added `/admin/system/recovery` for infrastructure-admin analysis, preview and narrowly-scoped repair of scheduler/outbox/dispatch/Redis telemetry state.
- Hardened queue recovery so `PROCESSED` `QUEUE_PUBLISH` rows are treated as successful RabbitMQ dispatch receipts; confirmed broker deliveries are preserved instead of blindly duplicated.
- Added failed-outbox classification: terminal, orphan, superseded, probe, confirm-recoverable, invalid payload and unknown active failure. Unknown/ambiguous failures remain untouched.
- Added safe outbox reconcile and missing-dispatch repair with CSRF, OWNER/ADMIN infrastructure authorization, confirmation and rate limits.
- Extracted publishing scheduler into a shared service used by both cron and Web Recovery Center, retaining MariaDB advisory locking. Added separate recurring-cron heartbeat/history so a manual web run cannot falsely prove cron health.
- Updated Redis readiness to separate server memory/cumulative eviction counters from the selected EP-DI database/namespace. Historical evictions alone no longer permanently degrade readiness; optional baseline tracks subsequent eviction delta.
- Updated processing readiness to use stale RUNNING heartbeat/recent failures rather than original queue creation age.
- Updated health/processing UI to route repair work through Recovery Center instead of broad redispatch.
- Added six admin recovery routes. No database migration, no RabbitMQ/Supervisor topology change, no Reader/PWA/Offline Vault behavior change.

## 2.0.1.1 — Service Worker & PWA Install Hotfix

- Fixes `Response.clone()` race in Service Worker static-asset caching that could throw `Response body is already used`.
- Static fetch handling now clones the network response immediately before asynchronous CacheStorage work.
- Custom `beforeinstallprompt` interception now runs only on pages that actually render the PWA Install button; normal pages no longer suppress the browser install UI unnecessarily.
- Preserves encrypted Offline Vault behavior, routes, database schema, processing and Reader navigation.
- No SQL migration and no Supervisor/RabbitMQ change.

## 2.0.1 — Reader Navigation & Appearance Polish

- Replaces the guest `Sign in` text link with an accessible icon action while retaining the authenticated `/account` icon.
- Adds a `/admin` shortcut icon only when the active tenant membership has an existing admin-authorized role; server-side RBAC remains authoritative.
- Adds Publisher Control settings for public appearance default (`Light`, `Follow device`, `Dark`) and whether the public theme switcher is shown. Default is `Light`.
- Keeps Publisher Console appearance independent from public/Reader appearance.
- Adds a compact, horizontally swipeable Reader edition-date strip below the global header, scoped to the same publication + city + language and opening the selected edition at page 1.
- Adds Publisher Control toggle for the Reader edition-date strip and a generation-cached lightweight Reader edition index with no media joins.
- Refines Archive unavailable-date visual noise and automatically centers the selected date.
- Preserves Reader viewport geometry via a single edition-strip height variable, including responsive and dark-mode states.
- No database migration, route addition/removal, worker-topology change or Offline Vault cryptography change.

## 2.0.0 — Production Stable

- Promotes the verified v2.0.0-rc.3.1.1 codebase to the immutable v2 stable release.
- No database migration, route change, permission change, processing change, publishing change, Reader change, Offline Vault change, or semantic-news behavior change.
- Stable promotion updates VERSION, PWA/service-worker cache keys, release guards, manifests, route/schema inventories, deployment/rollback documentation, and acceptance records.
- Retains the confirmed publication newsstand horizontal rail and non-stretched THUMB cover behavior.
- Production promotion remains subject to live `/admin/system/health` READY status and completion of environment-specific backup/restore and device/browser acceptance items.

## 2.0.0-rc.3.1.1 — Publication Newsstand Rail Fix

- Fixed publication landing pages such as `/epaper/civic-mirror` still using the legacy auto-fill Newsstand grid.
- Publication editions are now horizontally swipeable/trackpad-scrollable with mandatory scroll snap.
- Fixed stretched/cropped publication covers by switching that surface to `object-fit: contain` with stable newspaper card widths.
- Added responsive mobile rail widths, keyboard focus treatment and reduced-motion handling.
- No routes, schema, processing, Reader, Offline Vault or admin behavior changed.

# Changelog

## 2.0.0-rc.3.1 — Public Newsstand Polish
- Added restrained blurred editorial glow behind Today’s Edition copy.
- Added 3 px white cover edge and 3° hover/focus swing with reduced-motion support.
- Normalized Recent Editions horizontal swipe rail and corrected cover aspect handling.
- Reworked Archive results to 3-column desktop / 2-column tablet / 1-column mobile with non-stretched covers.
- No routes, schema, Reader, Offline Vault, processing or admin workflow changes.

## 2.0.0-rc.3 — Final Acceptance & UX/Defect Polish

- Tightened the stable-promotion automated gate so Production Readiness must be fully `READY`; `DEGRADED` no longer counts as launch approval.
- Added a browser-local manual acceptance matrix for backup/restore, publishing, Reader, encrypted offline, public Newsstand/Archive, Publisher Console, SEO/discovery and recovery/observability.
- Fixed collapsed `••• More` menus incorrectly losing less-common actions because visibility was measured while the popover was closed.
- Added form busy/disabled submission feedback to reduce accidental duplicate operations.
- Raised operational metadata/status legibility and mobile icon touch targets in the Publisher Console.
- Improved publication cover-card accessibility/CLS metadata while preserving strict THUMB delivery.
- Generalized Release Readiness artifact integrity to validate the build manifest for the current VERSION.
- No database migration, no route addition and no worker-topology change.

## 2.0.0-rc.2 — Production Hardening & Launch Readiness

- Added a real Production Readiness Center with READY / DEGRADED / ACTION_REQUIRED / REVIEW states backed by live read-only probes.
- Added MariaDB/Redis/RabbitMQ/Supervisor/scheduler/storage/processing/cache/Offline Vault/discovery/security/release readiness signals.
- Added stuck processing detection, scheduler heartbeat attention and transactional-outbox age/failure visibility.
- Added privacy-safe in-process DB query observability and published query-budget targets.
- Added rate limits for analytics, search, Reader progress and encrypted Offline Vault endpoints while preserving existing login/clipping/processing limits.
- Added scheduler advisory locking to prevent overlapping minute-cron runs and richer durable heartbeat/catch-up telemetry.
- Expanded Error Center correlation and Storage/Backup operational guidance.
- Explicitly treats Cloudways backup/restore and cross-device/browser acceptance as manual REVIEW items instead of fabricating automated pass states.
- No database migration, no worker-topology change and no public/Reader feature expansion.

## 2.0.0-rc.1.2 — Public Newsstand & Archive Experience

- Rebuilt `/` as a live-data digital newsstand with responsive current-edition cover delivery, recent-edition rail, publication rail and Archive date CTA.
- Added cached month/date availability to `/archive` with month navigation, Today/Yesterday/Last 7 days/All Year shortcuts and filter-preserving date links.
- Enforced strict THUMB delivery for public listing cards; PREVIEW/HIGHRES are no longer silent fallbacks for small covers.
- Homepage hero uses THUMB + PREVIEW responsive sources and never HIGHRES.
- Added cover dimensions, lazy decoding and missing-thumbnail placeholders to reduce transfer size and layout shift.
- No database migration and no worker topology change.

## 2.0.0-rc.1.1 — Publisher Operations Console UX

- Reorganized the admin information architecture around publishing operations.
- Added reusable CRUD/archive/restore icon actions with accessibility labels and consistent semantics.
- Refined Editions, Reader Navigation, dashboard, tables, statuses, forms, responsive behavior and application shell.
- Added `/admin/` compatibility normalization to the existing `/admin` dashboard route.
- No database migration and no backend workflow/permission changes.

# EP-DI Changelog

## 2.0.0-rc.1 — Semantic News SEO + AI Discovery + E-E-A-T Trust Infrastructure

- Added human-reviewed semantic Article publishing with optional OCR source import; raw OCR is never automatically indexable.
- Added author/newsroom profiles, topics and reusable entity graph with tenant-safe article relationships.
- Added crawlable Article/Author/Topic/Entity pages with canonical URLs, NewsArticle/Organization/ProfilePage/collection structured data and multilingual hreflang alternates.
- Added regular sitemap index plus static/articles/authors/topics/entities/editions sitemaps and rolling two-day Google News sitemap.
- Added Editorial Trust Center covering masthead, editorial standards, corrections, fact-checking, ethics, AI-use, advertising, ownership and newsroom contact.
- Added article-level source notes, correction notes and AI-assistance disclosure controls.
- Added revisioned SEO invalidation and asynchronous discovery-cache warming on semantic/trust changes.
- Preserved beta.2.1 multi-layer cache, beta.2 encrypted Offline Vault, Reader parity and RabbitMQ/Supervisor topology.
- Added additive rc.1 semantic database migration managed by Schema Doctor / Upgrade Center.


## 2.0.0-beta.2.1 — Multi-Layer Cache & Delivery Engine

- Added L0 request-memory → L1 Redis → L2 atomic filesystem read-through cache with stale-while-revalidate, TTL jitter and stampede locks.
- Added immutable published Edition Manifest v3 with Redis revision pointers, atomically written filesystem fallback and recent-edition warming.
- Added revisioned public SSR read-model caching for editions, publication/master data, archive filters, highlights and search.
- Added generation-based invalidation for settings, navigation, public editions, master data, highlights and SEO configuration.
- Added durable `CACHE_WARM` outbox events so publish/update flows can warm edition/public caches after invalidation without blocking the publisher request.
- Added media-metadata caching plus immutable signed-media HTTP cache headers while preserving Service Worker `no-store` protection for `/media/*` and Offline Vault APIs.
- Added Publisher Console → Performance & Cache observability for Redis hit rate/memory, runtime filesystem cache, manifest storage/revisions and generation counters.
- Added scoped Warm, Cleanup, Purge read cache and Rebuild published manifests operations for infrastructure administrators.
- Added optional `bin/cache-maintenance.php` generation/manifest cleanup and warm CLI.
- Added immutable browser/CDN cache policy for versioned assets and explicit no-cache policy for `service-worker.js`.
- Encrypted beta.2 IndexedDB Offline Vault remains unchanged; protected newspaper pages are not persisted as plaintext Service Worker cache entries.
- No database migration. Supervisor workers should be restarted after deployment so outbox/cache-warm runtime code is loaded.

## 2.0.0-beta.2 — Reader OS + Library + Encrypted PWA Offline Vault

- Rebuilt the signed-in consumer area as Reader OS with Continue Reading, capability summaries, recent history, Library and Devices.
- Added a device-bound encrypted Offline Vault for eligible recent editions using ECDH P-256 + HKDF-SHA256 + AES-256-GCM.
- Added rolling 7-day/offline-edition policy settings with entitlement-aware limits, hard expiry, device/edition revocation and logout wipe.
- Replaced legacy protected-media Cache Storage persistence with ciphertext-only IndexedDB storage and static offline library/reader shells.
- Added beta.2 Offline Vault database schema plus state-aware Schema Doctor repair.
- Hardened service worker protected-media handling and purged historical Reader media caches.
- Added public backup-artifact deny rules and removed historical public backup files from the full package.
- Preserved beta.1.2 Publisher Console, Editions Operations, Processing Recovery and alpha.2.3 runtime topology.

## 2.0.0-beta.1.2 — Processing Control & Recovery Profiles
- Extended Publisher Control → Processing with grouped PDF, generated-media, OCR, fallback and recovery controls while retaining current beta.1.1 behavior.
- Exposed runtime WebP quality defaults that were previously hard-coded.
- Added Recommended, High fidelity and Low-memory recovery form presets.
- Added opt-in bounded automatic rescue after normal RabbitMQ retries, with configurable recovery DPI/tile/OCR ceilings.
- Manual Reprocess now honors the publisher smart-correction default and Processing History surfaces the effective recovery profile.
- No database migration; Supervisor worker restart required.

## 2.0.0-beta.1.1 — Editions Operations
- Rebuilt `/admin/editions` around separate Processing, Publish Status and Schedule states with contextual row actions.
- Added safe Reprocess recovery with default, custom and conservative error-aware recommended settings; prior processing attempts remain intact.
- Added persistent `MANUAL`, `AUTO_READY` and `SCHEDULED` publishing intents with timezone-aware scheduling.
- Scheduler publishes only successfully processed READY editions and now reports a System Health heartbeat.
- Added low-query latest-processing summaries plus scheduling/recovery indexes.
- Extended Schema Doctor/Upgrade Center for the beta.1.1 migration.
- Preserved beta.1 Publisher Console and alpha.2.3 RabbitMQ/Supervisor topology.

## 2.0.0-beta.1 — Publisher Console & Reader Navigation Experience
- Introduced isolated premium Publisher Console design system and responsive shell.
- Reworked operational Dashboard into Status, KPIs, Attention Center, Common Tasks and Recent Content.
- Reworked Reader Navigation into a scope-first editor with responsive compiled preview and compact versioned rows.
- Added read-only Plans & Entitlements capability console backed by Digiti Platform state.
- Consolidated Analytics counters into one SQL round trip and enriched Error Center without extra DB queries.
- Retained alpha.2.3 Redis/RabbitMQ/Supervisor runtime, schema doctor and Reader performance foundation.
- No database migration.

## 2.0.0-alpha.2.3

- Added secured Supervisor Setup & Worker Doctor at `/admin/system/infrastructure/supervisor`.
- Generates server-specific worker commands and a credential-free downloadable Supervisor config from the actual deployed path.
- Added `bin/supervisor-preflight.php` to verify the CLI runtime, RabbitMQ, AMQP client, Redis and application-owned log path.
- Fixed worker-health false negatives by merging Redis heartbeat keys with the recent MariaDB heartbeat mirror instead of returning early on an empty Redis scan.
- Cross-checks RabbitMQ consumer counts against heartbeat counts and distinguishes `NOT_RUNNING`, `HEARTBEAT_MISSING`, `BROKER_CONSUMER_MISSING`, `PARTIAL` and `HEALTHY`.
- Ships/repairs `storage/logs`, which Supervisor needs for configured stdout log files.
- Throttled idle Redis worker-heartbeat writes while retaining short expiry and MariaDB recovery mirroring.
- Dashboard worker warnings now route directly to Worker Doctor.
- No database migration.

## 2.0.0-alpha.2.2

- Fixed Upgrade Center `Invalid UUID` failure after schema repair.
- Hardened audit UUID normalization and non-UUID resource references.
- Added previous schema-repair marker visibility.
- Hardened correlation GUID validation.
- No database migration.

## 2.0.0-alpha.2.1

### Web Upgrade & Schema Doctor hotfix
- Added secured `/admin/system/upgrade` Upgrade Center for canonical infrastructure admins.
- Added `/upgrade-check.php` convenience entry point that redirects into the authenticated Upgrade Center.
- Detects missing v2.0.0-alpha.1 settings index and v2.0.0-alpha.2 Reader Navigation tables/columns/indexes.
- Applies only known missing migration steps and serializes repairs with a MariaDB advisory lock.
- Adds dashboard Attention Center warning linking directly to Upgrade Center when schema changes are pending.
- Clears runtime/dashboard schema caches after repair and aligns `storage/installed.lock` when writable.
- Added `bin/schema-check.php` for CLI/SSH diagnostics.
- No new business-data migration beyond the already-required alpha.1/alpha.2 database changes.


## 2.0.0-alpha.2

- Added the versioned Reader Navigation publishing engine with Global → Publication/City/Language scoped fallback.
- Public Reader Navigation now resolves Redis → atomically compiled file → safe defaults without requiring MariaDB on the normal render path.
- Added draft editing, built-in/custom destinations, scheduling, visibility, reordering, atomic publish, immutable version history and append-only rollback.
- Added Redis SCAN utilities and Redis-first Supervisor worker liveness with MariaDB fallback.
- Added the short-lived Publishing Runtime snapshot and dashboard Attention Center/quick operations.
- Added cross-node cache invalidation through the transactional outbox; edition publishing invalidates dashboard and Reader-state caches.
- Added publisher-configurable protected-media token bucketing and X-Accel-Redirect runtime configuration.
- Added v2 alpha.2 database migration, build guard and cumulative release checks.
- Kept encrypted Offline Vault, full Publisher Console redesign and semantic article/SEO layer scheduled for later v2 milestones.

## 2.0.0-alpha.1

- Started the Publisher OS v2 performance architecture while retaining v1.1.9.11 as the recovery baseline.
- Removed major Home, Archive, Reader and Library page-asset N+1 query patterns.
- Added request-local + Redis settings-scope caching, short-lived dashboard/account snapshots and Reader resume/state caching.
- Added viewport-only Deep Zoom tiles, shared Reader prefetch consumption and frame-coalesced Reader rendering.
- Added Redis-first worker heartbeats, throttled MariaDB liveness persistence and bounded Supervisor worker recycling.
- Centralized build/asset versioning and service-worker cache busting.
- Added lean PWA shell, public network-first page cache, offline fallback and install shortcuts.
- Added media token bucketing plus optional Nginx X-Accel-Redirect offload.
- Added SSR language/JSON-LD hooks for the semantic SEO/AI-discovery layer.
- Added the v2 release guard, performance index upgrade and locked v2 blueprint.

## 1.1.9.11

- Removed the remaining live login-page upper, middle, and lower blank zones.
- Eliminated the legacy auth panel wrapper from layout geometry.
- Converted auth content to one intrinsic-height direct-flow stack.
- Sanitized legacy inline form geometry before rendering.
- Preserved rotating editorial quotes immediately after the security note.
- Bumped auth/service-worker cache version to 1.1.9.11.
