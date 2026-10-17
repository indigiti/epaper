# Build Manifest — ePaper SaaS v1.1.5

## Mandatory baseline
**ePaper SaaS v1.1.4.1 — Source Page QR Hotfix**

This is an overwrite hotfix for an existing v1.1.4.1 installation. The uploaded v1.1.4.1 hotfix was used as the source baseline for every protected file modified here.

## Source Page QR protections
- `app/Services/QrCode.php` remains byte-for-byte identical to the uploaded v1.1.4.1 baseline.
- `app/Services/ClipService.php` remains byte-for-byte identical to the uploaded v1.1.4.1 baseline.
- `BRANDING_VERSION = source-qr-v1` retained.
- branded clipping source-page QR still targets the exact source edition/page.
- clipping showcase Source Page context retained.
- separate Scan to open clipping QR retained.
- server-side seven-day clipping expiry remains enforced.

## v1.1.5 runtime changes
- `app/App.php`: dedicated Brand & Identity upload flow plus safe Admin/Account CRUD routes and mutation handlers.
- `app/Core/View.php`: dark-logo support, Apple Touch icon, v1.1.5 cache busting, CRUD interaction script.
- `public_html/assets/app.css`: brand asset manager, record action menus, CRUD/account management UI.
- `public_html/assets/crud.js`: confirmations, record-menu behavior, brand preview/color synchronization.
- `public_html/service-worker.js`: v1.1.5 cache alignment and CRUD script precache.

## No schema/worker changes
- No MariaDB migration.
- No RabbitMQ topology change.
- No Supervisor change.
- No processing pipeline change.
- No Digiti Platform contract change.
