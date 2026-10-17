# Build Manifest — v1.1.9.3

Base: v1.1.9.2

Changed runtime files:
- `app/Core/View.php` — cache-buster/runtime version only
- `public_html/assets/app.css` — auth geometry + typography hardening
- `public_html/service-worker.js` — release/cache version only

Release tooling/docs:
- `bin/release-check.php`
- `tests/auth-v1.1.9.3-browser.py`
- `tests/build-guard-v1.1.9.3.php`
- v1.1.9.3 release documentation

No DB migration. No queue/worker changes. No QR or clipping service changes.
