# EP-DI v2 — Locked Product & Engineering Blueprint

## Recovery baseline

`v1.1.9.11` remains the immutable recovery baseline until v2 production verification is complete.

## Release identity

**EP-DI v2.0.0 — Publisher OS Performance Lock**

The release is built around seven permanent pillars:

1. **Performance Core** — MariaDB query budgets, joined reads, Redis acceleration, direct media delivery, asset/build coherence.
2. **Async Publishing Engine** — RabbitMQ durable work transport, dedicated Supervisor workers, retry/dead-letter/recovery, bounded worker lifetime.
3. **Publisher Console** — operational dashboard, Attention Center, Publisher Control, plans/typed entitlements, diagnostics and role-aware navigation.
4. **Reader Platform** — Reader OS, Library, predictive Reader, scoped Reader Navigation, versioned navigation publication.
5. **PWA Offline Vault** — installable Reader, encrypted-at-rest edition chunks, rolling last-seven-day entitlement, device-bound licenses, storage/expiry management.
6. **Discovery Engine** — semantic articles, OCR/article search, author/topic/entity pages, multilingual SEO, Google News/search/AI-ready SSR.
7. **Trust & Observability** — editorial standards, corrections/AI/advertising/ownership policies, authorship, security, request/query/cache/worker telemetry and release gates.

## Data responsibilities

- **MariaDB:** canonical durable business data and transactional truth.
- **Redis:** repeated-read acceleration, compiled snapshots, short-lived reader state/resume, settings, entitlement snapshots, liveness, rate limits and locks.
- **RabbitMQ:** durable asynchronous job transport.
- **Supervisor:** worker lifecycle and process recovery.
- **Media storage/CDN/Nginx:** high-volume bytes; PHP authorizes access but should not remain the long-term byte-stream bottleneck.

Redis is not a second database and is not the replacement queue for RabbitMQ.

## Locked query budgets

Target budgets for production verification:

| Surface | Target DB queries |
|---|---:|
| Public home | ≤ 6 |
| Archive | ≤ 5 |
| Edition/Reader boot | ≤ 7 |
| Reader page state on Redis hit | 0–1 |
| Reader page state on miss | ≤ 3 |
| Reader OS | ≤ 8 |
| Library | ≤ 8 |
| Admin dashboard | ≤ 10 |
| Publisher settings | ≤ 6 |
| Published compiled navigation | ideally 0 |

Every critical query should be validated with `EXPLAIN`/`EXPLAIN ANALYZE` against representative production data before v2 production lock.

## PWA Offline Vault contract

The final encrypted offline implementation must not store premium newspaper pages as ordinary plaintext Cache Storage resources.

Target flow:

`published edition → offline pack worker → per-edition content key → authenticated encryption (AES-256-GCM) → encrypted page/OCR chunks → signed offline license → device-bound wrapped key → IndexedDB encrypted vault → on-demand Web Worker decryption → render → discard plaintext`

Policy defaults:

- `offline_window_days = 7`
- rolling access expires from edition date, not download date
- original PDF is not packaged for offline delivery
- hard license expiry works without network
- online revalidation handles subscription/device revocation
- logout removes device keys and vault material
- expired/corrupt packs are purged/rejected
- browser PWA protection is extraction resistance, not hardware-grade DRM

## Reader Navigation contract

One versioned navigation definition drives desktop, tablet and mobile public Reader surfaces.

Scope precedence:

`Global → Publication → City → Language`

Publishing workflow:

`Edit Draft → Preview → Publish → Compile immutable JSON/version → Redis/CDN → atomic activation → rollback available`

Sections:

- Header behavior
- Breaking / Live
- Primary menu
- Quick links
- Trending topics
- Topic library
- Version history

Published navigation should normally render without a MariaDB request.

## SEO / AI discovery contract

The ePaper image Reader remains an application; discoverable content becomes semantic SSR HTML.

Planned public entities:

- `/edition/...`
- `/article/...`
- `/topic/...`
- `/author/...`
- `/city/...`
- language-specific URLs
- Editorial Trust Center pages

Production semantic pages must support accurate canonical URLs, meaningful descriptions, article/author/publisher structured data where applicable, sitemap indexes, a current news sitemap, language/hreflang, crawlable body text and editorial timestamps.

E-E-A-T is implemented through real signals: original reporting/experience, named expertise, publication/author authority and especially trust — ownership, masthead, newsroom contact, editorial standards, correction policy, fact-checking/ethics, advertising disclosure and AI-use policy. No fake E-E-A-T score, keyword-stuffed FAQ farm or invented structured data.

## Performance targets

Internal v2 objectives to validate in production-like testing:

- LCP < 1.8 s
- INP < 150 ms
- CLS < 0.05
- cached public TTFB p95 < 250 ms
- uncached public TTFB p95 < 450 ms

These are test gates, not guaranteed numbers until measured on the target Cloudways/server/CDN configuration.

## Required observability

Record or expose at minimum:

- request duration / request ID / tenant / route / release
- DB query count and DB time
- Redis reads/hits/misses/hit rate/errors
- RabbitMQ queue depth and consumers
- worker heartbeat / memory / recycle count
- edition total processing time and stage times
- media response/offload status
- PWA install/offline/vault failures
- client JS errors
- Core Web Vitals

Admin-only performance HUD may expose request/query/cache diagnostics; public users must never see internal operational data.

## Security & accessibility gates

v2 production lock includes CSP/HSTS/secure cookies/SameSite/CSRF/session rotation/rate limits/tenant isolation/signed media/device management/audit trails/secret rotation/backup verification plus WCAG 2.2 AA-oriented keyboard, focus, contrast, reduced motion, zoom and 44px touch-target validation.

## Milestone implementation status

### v2.0.0-alpha.1 — Performance Foundation — complete

Implemented joined/N+1 reductions, Redis repeated-read caching, bounded workers, Reader tile/prefetch optimizations, lean PWA shell, media offload foundation and SSR discovery hooks.

### v2.0.0-alpha.2 — Runtime & Publishing Engine — complete engineering build

Implemented compiled Reader Navigation with scoped fallback and immutable versions, scheduled zero-DB public activation/expiry, Redis-first worker liveness, short-lived operational dashboard snapshots, durable tagged cache invalidation through the transactional outbox, and protected-media runtime controls.

### Remaining major milestones

- `v2.0.0-rc.1` — semantic News/SEO/AI discovery + E-E-A-T trust infrastructure.
- `v2.0.0-rc.2` — security, accessibility, Core Web Vitals and production hardening.

### v2.0.0-alpha.2.1 — Web Upgrade & Schema Doctor — complete hotfix

- Adds a canonical-host, infrastructure-admin-only Upgrade Center at `/admin/system/upgrade`.
- `/upgrade-check.php` is the simple web entry point for server upgrades.
- The doctor checks MariaDB base readiness, the alpha.1 settings index, alpha.2 Reader Navigation tables/columns/indexes, Redis connectivity, storage/cache writability and installed build marker.
- Known missing alpha.1/alpha.2 migration steps are repaired idempotently; unknown base-schema damage is intentionally blocked from automatic repair.
- Dashboard Attention Center links schema drift directly to Upgrade Center.
- CLI parity: `php bin/schema-check.php`.


### v2.0.0-alpha.2.2 — Upgrade Center UUID Safety Hotfix — complete

- Fixes `Invalid UUID` after alpha.2.1 schema repair.
- Non-UUID audit references are retained in metadata instead of UUID columns.
- Upgrade Center shows the previous repair marker so operators can verify whether the migration already completed.
- No new database migration.
### v2.0.0-alpha.2.3 — Supervisor Bootstrap & Worker Doctor — complete

- Adds `/admin/system/infrastructure/supervisor` for server-specific Supervisor installation and liveness diagnosis.
- Redis worker heartbeats are supplemented by the recent MariaDB mirror to avoid false all-down states during transient Redis/prefix changes.
- Broker consumer counts are cross-checked independently from application heartbeats.
- `storage/logs` is guaranteed as an application-owned prerequisite for Supervisor stdout logs.
- Idle heartbeat writes are throttled to reduce Redis churn while preserving fast failure detection.
- No database migration.


---

## v2.0.0-beta.1 — Publisher Console & Reader Navigation Experience

Status: **BUILT / pre-production verification required**

Beta.1 locks the approved admin art direction on top of the alpha.2.3 runtime foundation. The release is intentionally a presentation/product-experience layer and does not add a database migration.

### Locked console information architecture
- Overview → Dashboard
- Content → Editions, Publications, Highlights, Master Data
- Discovery → Reader Navigation, Analytics
- Audience → Subscribers, Plans & Entitlements
- Operations → Processing, Diagnostics, Error Center, Storage/Backups, Upgrade/Infrastructure where authorized
- System → Publisher Control, Roles & Access, Audit Trail, Release Audit

### Locked design rules
- Calm, high-density publisher console rather than a generic admin template.
- Tenant accent is restrained; operational state colors remain semantic.
- One isolated admin design system (`admin-beta.css`) instead of per-page CSS hotfix accumulation.
- Responsive desktop/tablet/mobile behavior and visible keyboard focus are release requirements.
- Reader Navigation retains accessible button-based ordering even when drag-and-drop is added later; drag can never be the only reorder mechanism.

### Runtime invariants retained
- MariaDB = canonical durable truth.
- Redis = read/cache/runtime accelerator.
- RabbitMQ = durable asynchronous transport.
- Supervisor = long-running process manager.
- Public Reader Navigation = Redis → compiled JSON file → safe fallback, normally 0 DB queries.
- v1.1.9.11 remains historical recovery baseline; alpha.2.3 remains direct beta.1 rollback baseline.

### v2.0.0-beta.1.1 — Editions Operations — complete engineering build

- `/admin/editions` uses separate processing/publishing/schedule state and contextual action hierarchy.
- Failed unpublished editions can start a new recovery pipeline using default, one-off custom or conservative recommended settings without changing Publisher Control defaults.
- Processing attempts remain append-only for operational history; live published-edition reprocessing is blocked until staged/atomic replacement exists.
- Add Edition supports manual publish, publish-when-ready and timezone-aware scheduled publishing.
- Automatic/scheduled publication is gated on a successful READY state and driven by the existing minute scheduler.
- System Health monitors scheduler freshness.
- Schema Doctor/Upgrade Center owns the beta.1.1 publishing intent/index migration.

### v2.0.0-beta.1.2 — Processing Control & Recovery Profiles — complete engineering build

- Publisher Control → Processing retains existing processing behavior while grouping PDF rendering, generated media, OCR, fallback and recovery policies.
- High-resolution, preview, thumbnail and Deep Zoom WebP quality are now tenant settings consumed by both RabbitMQ and legacy fallback processing paths.
- Manual Reprocess smart-correction preselection is publisher configurable.
- Optional automatic rescue is bounded, runs only after normal RabbitMQ retry exhaustion, and creates a new history-preserving recovery pipeline for unpublished editions.
- Recovery DPI ceiling, recovery tile size and recovery OCR timeout are publisher configurable.
- No new database migration and no new worker lane; existing Supervisor workers must be restarted after deployment.

### v2.0.0-beta.2 — Reader OS + Library + Encrypted PWA Offline Vault — complete engineering build

- Reader OS is the signed-in consumer home with Continue Reading, capability summaries, recent history, PWA install and direct Library/Devices access.
- Library consolidates saved articles, bookmarks, clips, history, encrypted offline editions and notifications without changing the canonical account data model.
- Eligible recent editions use a rolling publisher/entitlement-constrained offline window (default 7 days) and per-device limit (default 7 editions).
- Browser device identity uses a non-exportable ECDH P-256 private key; edition CEKs are wrapped with ECDH + HKDF-SHA256 + AES-256-GCM.
- Newspaper page bytes are encrypted server-side per request and persisted as AES-GCM ciphertext in IndexedDB; protected plaintext is never stored in Cache Storage.
- Static Offline Library/Reader PWA shells read/decrypt downloaded ciphertext without network access, while hard expiry, revocation, device management and logout wipe bound the lifecycle.
- Schema Doctor owns the beta.2 Offline Vault tables/columns/indexes migration.
- Historical public backup artifacts are denied/removed and old Reader media Cache Storage is purged at service-worker activation.

### v2.0.0-beta.2.1 — Multi-Layer Cache & Delivery Engine — complete engineering build

- Adds L0 request memory → L1 Redis → L2 atomic filesystem read-through caching with fresh/stale windows, TTL jitter and token-owned stampede protection that does not fall back to a duplicate filesystem rebuild while a healthy Redis lock is held.
- Adds immutable revisioned published-edition manifests with Redis current-revision pointers and filesystem fallback before MariaDB rebuild.
- Adds revision/generation invalidation for settings, navigation, public editions, master data, highlights and SEO configuration.
- Adds durable outbox-driven `CACHE_WARM` processing for post-publish edition/public-cache warming.
- Adds revisioned public SSR read-model caching for Home/Archive/publication/highlight/search data without converting public delivery into a client-only SPA.
- Adds media-metadata caching and explicit browser/proxy delivery headers while preserving beta.2 Service Worker `no-store` rules for protected media/Offline Vault APIs.
- Adds Publisher Console cache observability plus scoped Warm/Cleanup/Purge/Rebuild-manifest controls.
- Adds optional cache-maintenance CLI for obsolete-generation/runtime-cache/manifest cleanup and warming.
- Preserves the encrypted beta.2 IndexedDB Offline Vault unchanged.
- No database migration; long-running Supervisor workers should be restarted after deployment.

### v2.0.0-rc.1 — Semantic News SEO + AI Discovery + E-E-A-T Trust Infrastructure — complete engineering build

- Adds editorially reviewed semantic Articles, authors/newsroom profiles, Topics and reusable Entity pages.
- OCR is source material only; no raw OCR or generated text becomes indexable without explicit Article publication.
- Adds NewsArticle/Organization/ProfilePage/collection structured data, canonical URLs, multilingual hreflang and article Open Graph metadata.
- Adds regular and Google News sitemaps with revisioned multi-layer caching and asynchronous warming.
- Adds Publisher Control → Editorial Trust for masthead, standards, corrections, fact-checking, ethics, AI-use, advertising, ownership and contact.
- Adds additive semantic schema managed by the existing Upgrade Center.
- Preserves beta.2.1 cache/delivery architecture and beta.2 encrypted Offline Vault.


## v2.0.0-rc.1.1 — Publisher Operations Console UX

RC.1.1 is the visual-system freeze before production-hardening RC.2. It preserves RC.1 functionality while reorganizing the Publisher Console around operator intent. The canonical admin IA is now Publishing → Discovery → Audience → Publisher → Operations → Governance. Editions are the primary operational surface and expose View/Edit/context operation/Archive-or-Restore through a reusable accessible icon action system. Shared CRUD-heavy screens inherit the same action language. The application shell is intentionally low-chrome, neutral and publishing-focused rather than CRM-like. No schema changes are introduced.



## v2.0.0-rc.1.2 — Public Newsstand & Archive Experience

RC.1.2 freezes the public newsstand/archive visual baseline before production-hardening RC.2. The homepage is a live-data newspaper stand rather than a marketing template: current edition, recent editions, publications, Archive entry point, Highlights and Continue Reading remain SSR and cache-backed. Archive gains a filter-aware, generation-cached month/date availability rail with Today/Yesterday/Last 7 days/All Year navigation. Public list surfaces use strict THUMB assets; missing thumbnails render a lightweight placeholder instead of escalating to PREVIEW/HIGHRES. The homepage hero may use responsive THUMB/PREVIEW sources but never HIGHRES. No schema or worker changes are introduced.



## v2.0.0-rc.2 — Production Hardening & Launch Readiness

RC.2 is the feature-freeze hardening milestone. Production Readiness is an evidence-based operational surface, not a marketing score. Required automated probes cover application/schema, MariaDB, Redis, Platform binding, storage pressure, processing tools/backlog, RabbitMQ/outbox/Supervisor, publishing scheduler, multi-layer cache/manifests, encrypted Offline Vault, semantic discovery, security baseline, distributed throttling and release-artifact alignment. External Cloudways backup/restore and browser/device acceptance remain manual REVIEW items until an operator performs them.

Operational protections include stuck RUNNING-job detection (>20 minutes), scheduler advisory locking, durable scheduler heartbeat/catch-up payload, privacy-safe in-process query telemetry, queue/outbox age visibility, error/job correlation, bounded storage readiness scans and rate limiting on high-volume search/analytics/Reader/Offline Vault endpoints. RC.2 introduces no database migration and changes no Supervisor worker topology. RC.1.2 remains the frozen public UX baseline.

### Stable promotion gates

Before `v2.0.0` stable, deploy RC.2 and complete the external acceptance matrix: Cloudways backup plus restore rehearsal, desktop/laptop/tablet/mobile QA, encrypted-offline expiry/revocation/logout testing, live SEO/robots/sitemap/structured-data validation, and production performance observations. Any defects found here belong in RC.3 as acceptance fixes only—no new architecture.

### Next milestone
`v2.0.0-rc.3 — Final Acceptance & UX/Defect Polish` (only if RC.2 acceptance finds issues), followed by immutable `v2.0.0` stable.


## v2.0.0-rc.3 — Final Acceptance & UX/Defect Polish

RC.3 is the final pre-stable acceptance release and introduces no new product architecture. Automated stable promotion now requires Production Readiness to be fully `READY`; degraded runtime is not treated as launch approval. Release Audit includes a clearly non-authoritative browser-local manual acceptance matrix covering backup/restore, full publishing/reprocessing/scheduling, Reader breakpoint/device QA, encrypted offline lifecycle, public Newsstand/Archive, Publisher Console CRUD and accessibility, live SEO/discovery validation, and recovery/observability.

Verified packaged defects fixed in RC.3 include preservation of less-common `••• More` actions when the underlying `<details>` menu is collapsed, explicit busy/disabled form submission state to reduce duplicate operator actions, a practical operational metadata/touch-target floor in the Publisher Console, publication-card cover accessibility/dimension metadata, and dynamic current-version build-manifest validation in Production Readiness. No schema change, application-route addition or worker-topology change is introduced.

### Stable promotion gate

Promote to immutable `v2.0.0` only after: packaged RC.3 release gates pass; deployed Production Readiness is `READY`; the complete manual Final Acceptance Matrix has been executed on Cloudways and target browsers/devices; backup/restore rehearsal is documented; and no unresolved critical/high defect remains.

### Next milestone
`v2.0.0 — Production Stable` after live RC.3 acceptance.



## v2.0.0-rc.3.1 — Public Newsstand Polish
A presentation-only final public-surface patch after RC.3. Today’s Edition gains a restrained editorial glow and white paper edge with small reduced-motion-aware hover swing. Recent Editions remains horizontally swipeable with normalized cover frames. Archive uses a 3/2/1 responsive grid. Strict THUMB listing delivery and all RC.3 operational/readiness behavior remain unchanged. No database migration or route change.
## v2.0.0-rc.3.1.1 — Publication Newsstand Rail Fix

- Publication landing pages (`/epaper/{publication}`) use the same horizontal swipe/scroll-snap interaction language as Homepage Recent Editions.
- Publication cover cards use fixed responsive newspaper widths and `object-fit: contain`; publication pages no longer stretch or crop cover thumbnails.
- Strict THUMB delivery, routes, DB schema, Reader, Offline Vault, processing and Admin Console remain unchanged.

## v2.0.0 — Production Stable

The stable release promotes the exact rc.3.1.1 functional baseline. No schema, route, permission, processing, publishing, Reader, Offline Vault, caching, semantic-news or Publisher Console workflow is changed by the promotion itself. The stable delta is limited to release identity, PWA cache-version alignment, immutable package/checksum manifests, deployment and rollback runbooks, route/schema inventories, and the production acceptance record.

Stable operational contract:
- MariaDB remains the durable source of truth.
- Redis remains the read accelerator, runtime cache, distributed rate-limit/lock and heartbeat layer.
- RabbitMQ remains the durable asynchronous processing bus.
- Supervisor remains the worker lifecycle authority.
- Public edition cards use strict THUMB assets; Reader zoom uses HIGHRES/tiles; protected Offline Vault content remains encrypted and is not persisted plaintext in Cache Storage.
- Public navigation remains compiled/Redis-first with filesystem fallback.
- `/admin/system/health` and `/admin/system/release-audit` remain the production-readiness gates.

No `database/upgrade-v2.0.0.sql` migration exists.

## v2.0.1 — Reader Navigation & Appearance Polish

v2.0.1 is the first maintenance release after the checksum-locked v2.0.0 stable baseline. It preserves the durable publishing/Reader/Offline Vault architecture and adds only public navigation/appearance ergonomics. Guest authentication is represented by an accessible icon; authenticated users retain the Account icon and receive a Publisher Administration shortcut only when the active membership role already passes the existing admin-role policy. Server-side authorization is unchanged.

Public appearance policy is publisher-controlled through existing scoped settings: `appearance.public_color_mode` supports `light` (default), `system`, and `dark`; `appearance.public_color_switcher` controls whether readers may override the publisher default on the current browser. Admin Console appearance remains independent.

Reader edition switching is optional through `reader.edition_date_switcher` (default `on`). The compact strip appears below the global header, preserves the current publication + city + language variant, opens selected editions at page 1, and is backed by a generation-aware cached edition index that has no page/media joins. Archive selected-date centering and reduced unavailable-date copy use the same low-chrome navigation language.

No `database/upgrade-v2.0.1.sql` migration exists. Existing RabbitMQ/Supervisor topology, encrypted Offline Vault, semantic discovery, publishing workflow, strict THUMB public cards and Reader HIGHRES/tile delivery are unchanged.

## v2.0.1.2 — Web Recovery Center & Readiness Accuracy

The recovery model is now **Analyze → Classify → Preview → Safe Repair → Recheck**. A `PROCESSED` transactional outbox `QUEUE_PUBLISH` row is treated as a successful broker-dispatch receipt; an active DB job with such a receipt may legitimately be waiting in RabbitMQ and is not automatically duplicated. Safe reconciliation closes historical terminal/orphan/probe/superseded failures, recovers only the known php-amqplib publisher-confirm bookkeeping failure, and leaves unknown/invalid active failures unchanged. Missing-dispatch repair creates a new outbox event only when there is no `QUEUE_PUBLISH` history for that active job.

`/admin/system/recovery` is restricted to canonical-host infrastructure administrators (OWNER/ADMIN) and all mutating actions are POST + CSRF + rate-limited + audited. The web scheduler action calls the same `PublishingScheduler::runOnce()` service as the one-minute CLI cron; recurring cron health is recorded separately via `scheduler.last_cron` so a manual run cannot mask a broken cron. Redis health separates server memory/historical evictions from selected DB/EP-DI namespace metrics and can capture an eviction baseline without exposing dangerous `FLUSHALL` or `CONFIG SET` operations.

No v2.0.1.2 SQL migration exists. Existing RabbitMQ lanes, Supervisor worker counts, publishing workflows, Reader, PWA and encrypted Offline Vault are unchanged.

