> Current source release: **EP-DI v2.0.2.1 — Reader Back-Edition Navigator**. Read [UPDATE-2.0.2.1.md](UPDATE-2.0.2.1.md). No database migration is required.

# ePaper SaaS v1.1.4 — Premium Clipping Experience

This cumulative release ports the mature City ePaper clipping experience onto the SaaS-native Reader. It removes the browser confirmation prompt and replaces it with an editable clipping dialog, fixed seven-day expiry, publisher-branded clipping showcase, QR sharing, share analytics and real downloadable social formats.

## Clipping policy

- Title is editable before save.
- Visibility is limited to `PRIVATE` or `PUBLIC`.
- New clips expire exactly 7 days after creation; extension is intentionally unavailable.
- Organisation visibility is removed from the user workflow.
- Publisher branding is always included on shareable clipping artwork.
- Private clips are accessible only to the creating user; public clips can be shared directly.

## Premium clipping page

- Publisher logo/name, publication, edition date, page number and clipping URL
- Views, Shares and Downloads analytics
- WhatsApp, Facebook, X, Telegram and Copy Link actions
- QR code generated locally in the browser (no third-party QR service)
- Original WebP, PDF, Instagram Portrait/Landscape, Facebook, X/Twitter, WhatsApp, WebP and AVIF when supported
- Share formats prepare progressively after the clipping page opens
- Original newspaper page link retained

## Upgrade

No database migration, RabbitMQ change, Supervisor change or Platform change is required. Extract over the existing outer `public_html`, keep Webroot `public_html/public_html`, purge Varnish/browser cache and hard-refresh.

See `UPDATE-1.1.4.md` and `release/FINAL-REAUDIT-1.1.4.md`.

---

# ePaper SaaS v1.1.2

## Admin Appearance + Luxury Operations

This cumulative release adds per-user Publisher Admin Light/Dark mode, a compact DB/cache request telemetry HUD, and a premium System Health dashboard while preserving the City parity Reader/Account/Admin UX and RabbitMQ/Supervisor processing foundation.

See `UPDATE-1.1.2.md` and `release/FINAL-REAUDIT-1.1.2.md`.

---

# ePaper SaaS v1.1.0 — City Feature Parity Consolidation

This release keeps the SaaS-native architecture introduced in ePaper SaaS and formalizes **City ePaper v2.2.9.1** as the product behavior/UX regression baseline.

## Architecture retained

- Digiti Platform Product Tenant + custom-domain resolution
- tenant-scoped MariaDB repositories and UUIDv7 identifiers
- Redis cache/session/locks
- RabbitMQ + Supervisor processing lanes
- transactional outbox, retries and dead-letter queue
- central identity, subscriptions and entitlements
- request/error/audit correlation IDs

## City parity consolidated

- content-first Reader with Fit Page / Fit Width, pointer-centered zoom, scroll-zoom preference, bounded pan, mobile pinch, swipe, keyboard, Deep Zoom and seamless page navigation
- final Clip Mode workflow: active icon, crosshair, temporary hint, Save / Re-select / Cancel, Esc cancellation
- Smart Article, browser TTS, OCR search, Bookmark, Offline, Share, Resume/History
- Newsstand-style home, Continue Reading, highlights and richer recent-edition cards
- Archive filters for publication, city, language, year and date
- Account Library: Saved Articles, Bookmarks, Clips, History, Offline and Notifications
- Publisher Control navigation aligned to City: Content, Publishing, Audience, Settings, Access, Operations and Account
- grouped Appearance / Brand & identity / SEO / Reader / Processing / Clipping settings
- Admin, Reader and Authentication theme families
- Processing detail drill-down, Storage & backups, Performance and Release Audit dashboards
- Ctrl/Cmd+K Publisher command palette and mobile admin navigation drawer
- edition-language-aware OCR profile (English, Marathi, Hindi)

## Reader media policy

- Original PDF is retained privately.
- Internal PDF render JPEG is a private processing intermediate only.
- Reader HIGHRES, PREVIEW, THUMB and Deep Zoom tile assets are WebP.
- Clip image export is WebP.
- OCR is stored as hOCR + extracted text.

## Release manifests

See `release/` for routes, capabilities, settings, analytics, tenant data, Reader behavior, processing stages and City compatibility manifests.

## Install / upgrade

This ZIP is cumulative and safe to extract over the existing ePaper SaaS application. It does not package `config/config.php` or `config/runtime.php`, so live credentials are not overwritten.

Keep Cloudways Webroot:

`public_html/public_html`

After upload, restart long-running Supervisor jobs and purge Varnish/browser cache so workers and Reader assets load v1.1.0.

## Verification

Run:

`php bin/release-check.php`

Then open:

- `/health/ready`
- `/health/platform`
- `/health/queue`
- `/admin/system/release-audit`
- `/admin/system/infrastructure`

Release state is **TESTED**. Static/local guards plus a headless Chromium Reader smoke test pass; live Cloudways PDF processing and physical mobile/desktop device QA are still required before **VERIFIED**.

## v1.1.1 UX consolidation
The Admin and Account areas now use persistent workspace shells. Publisher navigation is grouped by workflow and presentation-role, Settings is consolidated in the sidebar with its existing internal tabs preserved, and the Account Library uses WebP preview cards. Reader primary navigation/header/pager remain visible during idle reading mode.


## v1.1.3 Reader UX

The Reader uses a fixed viewport with independent thumbnail scrolling, a Pages toggle beside Search, and Auto / Low Data / High Quality media delivery. See `UPDATE-1.1.3.md`.
