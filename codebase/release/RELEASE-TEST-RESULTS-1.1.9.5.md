# Release Test Results — v1.1.9.5

Status: TESTED (local/fresh-overlay), pending live Cloudways verification.

Passed:
- PHP syntax for `app/Core/View.php` and v1.1.9.5 build guard.
- Dedicated auth stylesheet loads after app.css and uses a unique filename/cache namespace.
- Browser layout checks at 2560×1440, 2048×1202, 1920×1080, 1600×900, 1440×900, 1366×768, 1280×720, 1024×768, 900×900, 768×1024, 430×932, 390×844, 360×800.
- Desktop story header, quote, form, submit button and security copy all remain inside their panels.
- Mobile/tablet shows sign-in first with publisher branding.
- Searchable Archive remains absent; exactly two feature rows remain.
- v1.1.4.1 protected QrCode/ClipService hashes unchanged.
- v1.1.5.1 Settings POST normalization retained.
- Guest clipping, smart social sharing, same-domain subscription, account bulk-clear and Reader Redis/prefetch guards retained.
- Service Worker namespace aligned to v1.1.9.5; Reader media cache remains bounded.
