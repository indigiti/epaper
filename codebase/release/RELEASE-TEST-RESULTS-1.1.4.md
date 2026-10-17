# Release Test Results — ePaper SaaS v1.1.4

State: **TESTED**

## Inventory
- Routes: 71 (18 public, 13 Reader/API, 7 account, 33 domain-admin)
- Capabilities: 135
- Settings: 46
- Analytics events: 20
- Tenant-owned SQL statements audited: 180
- PHP files linted: 52

## Passed checks
- PHP syntax across app/bin/public/tests
- app.js syntax
- reader.js syntax
- service-worker.js syntax
- UUIDv7 format and BINARY(16) roundtrip smoke tests
- route deletion guard
- capability deletion guard
- settings deletion guard
- analytics frontend/backend registry alignment
- tenant SQL isolation guard
- City ePaper compatibility/parity manifests
- Reader Fit Page/Fit Width/pinch/pan/seamless navigation/Clip Mode regression guards
- Deep Zoom/media route guards
- RabbitMQ/Supervisor/transactional outbox guards
- worker lock-busy requeue guard
- HIGHRES WebP policy
- CSRF/private-no-store regression guards
- premium clipping flow guards
- fixed seven-day clipping expiry
- Private/Public-only clipping visibility
- organisation field removal
- browser confirm removal
- clipping share/download analytics registration
- QR generation/decode smoke test
- cumulative v1.1.4 guard

## Environment-dependent checks not claimed
- Live Cloudways PDF/Imagick clipping format rendering
- AVIF codec availability
- Cross-device QR opening against production auth/session
- Real social-app handoff behavior
- Live analytics counter increments

These are part of the post-deployment verification checklist in FINAL-REAUDIT-1.1.4.md.
