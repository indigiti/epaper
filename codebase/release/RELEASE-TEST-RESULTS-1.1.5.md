# Release Test Results — ePaper SaaS v1.1.5

State: **TESTED**

- Source Page QR exact protected hashes: PASS
- Source Page QR decode: PASS
- PHP syntax: PASS
- CRUD JavaScript syntax: PASS
- Service Worker syntax: PASS
- Brand multipart/upload wiring guard: PASS
- Tenant-scoped destructive SQL guard: PASS
- Immutable Audit/Error/Processing deletion guard: PASS
- Admin CRUD route/action family guard: PASS
- Account CRUD route/action family guard: PASS
- Brand UI browser smoke: PASS
- Dark logo browser smoke: PASS
- Confirmation browser smoke: PASS
- Fresh ZIP integrity: PASS
- SHA256SUMS: PASS

Full live regression suite should also be run after this hotfix is overlaid on the complete v1.1.4.1 installation; `bin/release-check.php` automatically runs the existing full regression suite when its baseline manifests/tests are present.
