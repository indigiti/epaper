# Build Manifest — ePaper SaaS v1.1.6.2

Baseline: **v1.1.6.1**
Release state: **TESTED**

## Runtime changes
- `app/App.php` — account bulk clear hardening, Master Data workspace.
- `app/Core/View.php` — v1.1.6.2 asset/version alignment only.
- `public_html/assets/app.css` — editor drawer, Master Data tabs/list/search/mobile sheet.
- `public_html/assets/crud.js` — delegated confirms, shared editor drawer, Master Data tabs/search.
- `public_html/service-worker.js` — v1.1.6.2 cache version.
- `bin/release-check.php` — v1.1.6.2 build guard.
- `VERSION`, `manifest.json`, release docs/tests.

## Explicitly unchanged
- `app/Services/QrCode.php`
- `app/Services/ClipService.php`
- database schema
- Reader rendering/zoom/pan
- social thumbnail route behavior
- RabbitMQ/Supervisor/outbox
- Digiti Platform integration
