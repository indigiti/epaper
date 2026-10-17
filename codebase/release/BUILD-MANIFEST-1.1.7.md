# Build Manifest — ePaper SaaS v1.1.7

Baseline: **v1.1.6.2**
Release state: **TESTED**

## Runtime changes
- `app/App.php` — guest quota, guest creation, conversion metadata, post-login claim, guest noindex/claim banner.
- `app/Core/View.php` — per-page robots override and v1.1.7 guest module loading.
- `public_html/assets/guest-clipping.js` — second-attempt and subscription conversion gate.
- `public_html/assets/app.css` — guest free-clip, conversion sheet and claim banner UI.
- `public_html/service-worker.js` — v1.1.7 cache and guest module.

## Explicitly untouched
- `app/Services/QrCode.php`
- `app/Services/ClipService.php`
- MariaDB schema
- RabbitMQ topology
- Supervisor workers
- Reader media/Deep Zoom pipeline

The exact v1.1.4.1 Source Page QR baseline hashes remain release-blocking guards.
