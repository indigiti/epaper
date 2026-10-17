# Build Manifest — ePaper SaaS v1.1.0

Release: **ePaper SaaS v1.1.0 — City Feature Parity Consolidation**  
Behavioral baseline: **City ePaper v2.2.9.1**  
Architecture baseline: ePaper SaaS + Digiti Platform + MariaDB + Redis + RabbitMQ + Supervisor  
Release state: **TESTED**

## Scope

This is a cumulative release. It preserves the current SaaS-native architecture and consolidates the mature City ePaper Reader/Admin/product behavior into the new multi-tenant application.

## Protected inventory

- Baseline routes: 54
- v1.1.0 routes: 67
- Missing baseline routes: 0
- Baseline capabilities: 71
- v1.1.0 capabilities: 105
- Missing baseline capabilities: 0
- Previous settings: 21
- v1.1.0 settings: 46
- Missing previous settings: 0
- Tenant-owned SQL statements statically checked: 174
- Unscoped tenant-owned SQL statements found: 0

## Reader/media contract

- Original PDF: private, retained
- Internal renderer source: JPEG permitted only as private processing intermediate
- Reader HIGHRES: WebP
- Reader PREVIEW: WebP
- THUMB: WebP
- Deep Zoom tiles: WebP
- Clip image export: WebP
- OCR: hOCR + extracted text

## Processing engine

- MariaDB authoritative job/progress state
- Transactional outbox
- RabbitMQ durable transport
- Supervisor worker lifecycle
- Render lane
- Assets lane
- OCR lane
- Finalize lane
- 30s / 5m / 30m retry topology
- Dead-letter queue
- Publisher confirms initialized once per channel
- lock-busy delivery requeue protection
- queue-dispatch recovery
- edition-language-aware OCR profiles

## Reader parity contract

- Fit Page / Fit Width
- pointer-centered zoom
- Scroll Zoom preference
- bounded pan
- mobile pinch in/out
- swipe navigation
- keyboard navigation
- thumbnail navigation
- seamless page change + History API
- adjacent-page prefetch
- Deep Zoom
- Clip final workflow
- Smart Article + TTS
- OCR Search
- Bookmark / Share
- Resume / History
- Offline/PWA

## Admin/account parity

- Newsstand home + Continue Reading
- Archive filtering
- Account Library
- Publisher Control grouped navigation
- Appearance / Brand / SEO / Reader / Processing / Clipping settings
- Admin / Reader / Auth theme families
- Processing detail
- Storage / Performance / Health / Error / Audit / Release Audit
- Infrastructure Dashboard
- command palette
- mobile admin drawer

## Validation executed in build environment

- PHP syntax across all packaged PHP files: PASS (50 files)
- JavaScript syntax: PASS
- UUIDv7 + BINARY(16) roundtrip: PASS
- full baseline route deletion guard: PASS
- full baseline capability deletion guard: PASS
- full baseline settings deletion guard: PASS
- tenant SQL guard: PASS
- Reader parity static guard: PASS
- headless Chromium Reader smoke: PASS (12/12)
- analytics frontend/backend registry parity: PASS
- RabbitMQ/outbox/worker reliability guards: PASS
- WebP HIGHRES policy guard: PASS
- service-worker/version alignment: PASS
- schema duplicate-table guard: PASS

## Live verification still required before VERIFIED

- Digiti Platform live binding
- MariaDB / Redis / RabbitMQ / Supervisor live checks
- Infrastructure end-to-end probe
- one real PDF upload → READY processing run
- generated HIGHRES/PREVIEW/THUMB/Deep Zoom WebP inspection
- real mobile pinch/pan/swipe QA
- real desktop zoom/pan/edge-reachability QA
- Clip/Smart Article/OCR Search/Offline/Account smoke on live tenant

## Rollback

Take a Cloudways on-demand backup before upload. The package does not include `config/config.php` or `config/runtime.php`. Roll back application files to the previous verified snapshot and restart all ePaper Supervisor jobs.
