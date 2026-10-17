# Build Manifest — ePaper SaaS v1.1.6

Baseline: **v1.1.5.1 Settings Save Normalization Hotfix**
Protected baseline: **v1.1.4.1 Source Page QR Hotfix**

## Changed runtime files
- `app/App.php` — stable social thumbnail route; Reader/Clip Open Graph options.
- `app/Core/View.php` — Open Graph/Twitter/canonical rendering; accessible responsive brand markup; v1.1.6 asset versions.
- `public_html/assets/app.css` — responsive desktop/tablet/mobile logo sizing.
- `public_html/service-worker.js` — cache namespace/assets aligned to v1.1.6.
- `VERSION`, `manifest.json`.

## Explicitly unchanged
- `app/Services/QrCode.php`
- `app/Services/ClipService.php`
- database schema
- RabbitMQ/Supervisor
- processing pipeline
- Reader image processing
- Deep Zoom
- clipping source-page QR behavior
- v1.1.5 CRUD foundation
- v1.1.5.1 settings save normalization

## Storage policy
No SOCIAL_CARD asset type or 1200x630 derivative is generated. Sharing reuses existing `THUMB`, with existing `PREVIEW` as fallback.
