# Build Manifest — ePaper SaaS v1.0.1.6

Release type: Reader interaction hotfix
Baseline: ePaper SaaS v1.0.1.5

Changed runtime files:
- `public_html/assets/reader.js`
- `public_html/assets/reader.css`
- `app/Core/View.php` (Reader/app cache-buster only)
- `public_html/service-worker.js` (cache version only)
- `VERSION`

Protected-file review:
- `app/Core/View.php`: no layout/router/auth behavior changed; only asset query version bumped from 1.0.1.2 to 1.0.1.6.
- Reader behavior changes are isolated to viewport/zoom/pan/pinch/swipe coordination.
- Service worker logic unchanged except VERSION and shell URL cache-busters.

No changes:
- DB schema
- routes/APIs
- tenant isolation
- entitlements
- Platform integration
- RabbitMQ/Supervisor/outbox
- PDF/OCR processing
- media/token/tile endpoints
- clipping API/business rules
