# Final Re-audit — ePaper SaaS v1.1.6.1

Release state: **TESTED**
Baseline: **v1.1.6**
Protected baseline: **v1.1.4.1 Source Page QR Hotfix**

## Header branding acceptance

- Logo configured: exactly one publisher logo is rendered in the header.
- Logo configured: visible Site name is not repeated beside the masthead.
- Logo configured: logo `alt` text uses the configured Site name.
- No logo configured: Site name is rendered as the visible text fallback.
- Brand link retains an accessible `<Site name> home` label.
- Wide mastheads retain intrinsic aspect ratio.
- Desktop logo cap: 190px × 36px.
- Tablet logo cap: 150px × 30px.
- Mobile logo cap: 130px × 28px.

## Protected behavior

- v1.1.4.1 `QrCode.php` exact SHA-256 retained.
- v1.1.4.1 `ClipService.php` exact SHA-256 retained.
- Source Page QR behavior retained.
- v1.1.5 Brand/CRUD retained.
- v1.1.5.1 Settings save normalization retained.
- v1.1.6 Smart Social Sharing retained.
- No DB migration.
- No RabbitMQ/Supervisor change.

## Browser verification

Headless Chromium tested at 1440×900 and 390×844 for both logo-present and logo-absent states. All header-brand assertions passed.
