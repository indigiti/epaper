# ePaper SaaS v1.1.4.1 Build Manifest

Scope: clipping presentation only.

Changed runtime files:
- `app/Services/QrCode.php` — dependency-free Version 5-L QR encoder used for short source-page URLs.
- `app/Services/ClipService.php` — source-page QR compositing, branded/share asset version bump, expiry text removed from artwork.
- `app/App.php` — clipping showcase source-page context replaces visible expiry summary.
- `app/Core/View.php` — v1.1.4.1 asset cache-busters.
- `public_html/assets/app.css` — source-page context styling.
- `public_html/service-worker.js` — cache version alignment.
- `manifest.json`, `VERSION`.

No database migration. No RabbitMQ/Supervisor change. No Reader geometry change. No route removal.
