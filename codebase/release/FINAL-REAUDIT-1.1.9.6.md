# Final Re-audit — ePaper SaaS v1.1.9.6

State: **TESTED**

## Scope

v1.1.9.6 replaces the failed split/editorial authentication layout with a single centered responsive glass login card. It is intentionally isolated from the historical auth CSS cascade by a new stylesheet filename and unique `auth-simple-*` selectors.

## Browser geometry

Headless Chromium passed at 2560×1440, 2048×1202, 1920×1080, 1600×900, 1440×900, 1366×768, 1280×720, 1024×768, 768×1024, 430×932, 390×844, 360×800 and 320×720.

Checks include: card stays inside viewport, complete form/security text stays inside card, publisher brand stays visible, mobile card uses available width, and legacy split-auth elements are absent.

## Protected cumulative behavior

- v1.1.4.1 Source Page QR exact hashes retained.
- v1.1.5.1 Settings POST normalization retained.
- v1.1.6 Smart social sharing retained.
- v1.1.7 Guest clipping retained.
- v1.1.8 Redis Reader-state cache and predictive prefetch retained.
- Digiti Platform UI remains background-only.
- Same-domain `/subscribe` / `/account/subscription` behavior retained.
- v1.1.6.2 bulk account clear audit fix retained.

## Deployment boundary

No DB migration. No RabbitMQ/Supervisor restart. No production config/secrets packaged. `QrCode.php` and `ClipService.php` are intentionally absent from the hotfix ZIP.
