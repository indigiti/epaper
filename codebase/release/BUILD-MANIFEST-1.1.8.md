# Build Manifest — ePaper SaaS v1.1.8

Base: v1.1.7

Changed/runtime additions:
- app/App.php
- app/Core/View.php
- public_html/assets/app.css
- public_html/assets/guest-clipping.js
- public_html/assets/reader-prefetch.js
- public_html/service-worker.js
- VERSION
- manifest.json

Protected and intentionally untouched:
- app/Services/QrCode.php
- app/Services/ClipService.php
- v1.1.4.1 Source Page QR behavior
- v1.1.5.1 Settings normalization
- v1.1.6 Smart Social Sharing
- v1.1.6.1 header dedup
- v1.1.6.2 Account/Admin CRUD fixes
- v1.1.7 Guest Clipping conversion logic

No schema migration. No worker topology change.
