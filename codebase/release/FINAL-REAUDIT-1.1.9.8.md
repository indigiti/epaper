# ePaper SaaS v1.1.9.8 — Final Re-audit

## Result

State: TESTED.

v1.1.9.8 keeps the v1.1.9.7 content-fit glass login geometry and upgrades the rotating editorial quote into a dedicated glass quote card inside the authentication surface.

## Authentication UX

- Shared-reference style sky/soft-cloud background retained.
- Single centered content-fit glass login card retained.
- Publisher logo/name fallback retained.
- Sign-in icon, form, MFA field, and secure identity note retained.
- Rotating quote is now visually separated with an editorial quotation mark, stronger quote typography, quieter author line, subtle accent integration, and responsive spacing.
- Quote selection remains server-side with a 12-item pool and immediate-repeat avoidance through `epaper_auth_quote_index` in the active session.

## Browser QA

Headless Chromium passed at 1920×1080, 1600×900, 1440×900, 1366×768, 1280×720, 1024×768, 768×1024, 430×932, 390×844, 360×800, and 320×720.

Verified:
- no runtime errors;
- compact 440px maximum login width;
- content-fit height;
- quote card remains inside the login card;
- quote mark and author remain visible;
- submit button remains inside card;
- publisher brand remains visible;
- mobile width/viewport containment.

## Regression protection

The cumulative v1.1.9.8 build guard passed and retained:
- v1.1.4.1 Source Page QR exact hashes;
- v1.1.5.1 settings normalization;
- v1.1.6 smart social sharing;
- v1.1.6.2 account bulk-action audit behavior;
- v1.1.7 guest clipping;
- v1.1.8 Redis reader-state cache and predictive browser prefetch;
- same-domain subscription/navigation boundary with no user-facing `platform.digiti.in` literal.

The hotfix overlay does not package `app/Services/QrCode.php` or `app/Services/ClipService.php`.

## Runtime impact

No database migration. No RabbitMQ or Supervisor restart.
