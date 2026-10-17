# ePaper SaaS v1.1.4 — Final Re-audit

Release state: **TESTED**

## Scope

v1.1.4 consolidates the premium clipping workflow on top of the verified SaaS architecture and the City ePaper parity baseline. It does not replace Reader geometry, RabbitMQ/Supervisor processing, tenant isolation, Platform integration, Admin/Account shells, or the v1.1.3 Data Saver architecture.

## Clipping workflow verified

- Reader Clip Mode selection is retained.
- Browser `confirm("Clip saved. Open it now?")` is removed.
- Create Clipping dialog exposes only editable Title and Private/Public visibility.
- Organisation visibility is removed from new clipping creation.
- New clips use a fixed seven-day expiry; no extension control is exposed.
- Creation UI includes progress and an explicit Open Clipping transition.
- Premium `/c/{slug}` clipping page includes publisher branding, source edition context, Views/Shares/Downloads, social share actions, QR scan-to-open and download formats.
- Private clipping access remains owner-protected; Public clipping is link-accessible until expiry.
- Share/download actions are registered in the analytics catalog.

## Download/share formats implemented

- Original branded WebP
- PDF
- Instagram Portrait 1080x1350
- Instagram Landscape 1080x566
- Facebook Image 1200x630
- X/Twitter Image 1200x675
- WhatsApp Image 1080x1080
- WebP
- AVIF when the live ImageMagick/Imagick build supports AVIF

## Regression gates

- Baseline City/ePaper SaaS routes preserved.
- Baseline capabilities preserved.
- Baseline settings preserved.
- Reader Fit Page/Fit Width/pinch/pan/seamless navigation/Clip Mode/Smart Article/offline behavior retained.
- Media/Deep Zoom route families retained.
- RabbitMQ, Supervisor, transactional outbox and worker claim protections retained.
- HIGHRES reader media remains WebP.
- Tenant-owned application SQL remains tenant scoped.
- CSRF private/no-store authentication protections retained.
- Service worker/version alignment retained.

## Static/runtime-independent validation

- PHP syntax: PASS
- JavaScript syntax: PASS
- Service Worker syntax: PASS
- Core smoke: PASS
- v1.1.4 build guard: PASS
- Tenant SQL guard: PASS
- Reader parity: PASS
- Parity manifests: PASS
- QR generation/decode smoke: PASS
- Cumulative upgrade guard: PASS

## Live verification still required

The build environment cannot operate the user's live Cloudways browser/session, RabbitMQ production data, or Cloudways Imagick codecs. Before promoting this release to VERIFIED, test on `epaper.digiti.in`:

1. Create one Private clipping and one Public clipping.
2. Confirm title editing and only Private/Public visibility choices.
3. Confirm fixed seven-day expiry and no extension/Organisation fields.
4. Confirm clipping creation progress and Open Clipping transition.
5. Confirm publisher logo/name/date/page/URL branding on the generated clipping.
6. Scan QR on another device; Public opens directly, Private requires owner authentication.
7. Exercise WhatsApp, Facebook, X, Telegram and Copy Link.
8. Download Original, PDF and at least one social format.
9. Confirm Views/Shares/Downloads counters change appropriately.
10. Verify AVIF only if the live Imagick build reports AVIF support.

Only after these live checks should v1.1.4 be promoted from TESTED to VERIFIED.
