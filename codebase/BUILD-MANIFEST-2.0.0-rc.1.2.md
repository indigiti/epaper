# Build Manifest — EP-DI v2.0.0-rc.1.2

Release: **Public Newsstand & Archive Experience**  
Baseline: **EP-DI v2.0.0-rc.1.1**

## Functional scope
- Public homepage/newsstand presentation
- Archive month/date rail and cached date availability
- Strict-thumbnail public-listing delivery policy
- Responsive public newsstand/archive design system
- Service-worker/build version alignment

## Changed runtime files
- `VERSION`
- `app/App.php`
- `app/Services/PublicReadCache.php`
- `app/Services/TenantData.php`
- `public_html/assets/app.css`
- `public_html/service-worker.js`
- `public_html/offline.html`
- `public_html/offline-library.html`
- `public_html/offline-reader.html`
- `bin/release-check.php`

## Added release tests
- `tests/build-guard-v2.0.0-rc.1.2.php`
- `tests/public-newsstand-archive-static.php`

## Documentation
- `UPDATE-2.0.0-rc.1.2.md`
- `BUILD-MANIFEST-2.0.0-rc.1.2.md`
- `ROLLBACK-2.0.0-rc.1.2.md`
- `V2-BLUEPRINT.md`
- `CHANGELOG.md`

## Database
No schema change. No `upgrade-v2.0.0-rc.1.2.sql` exists.

## Preserved systems
- RC.1 semantic-news schema and Editorial Trust infrastructure
- RC.1.1 Publisher Operations Console UX
- beta.2.1 Multi-Layer Cache & Delivery Engine
- beta.2 Encrypted Offline Vault
- beta.1.2 Processing Control / Recovery
- alpha.2.3 Supervisor/RabbitMQ topology

## Release guardrails
- strict THUMB listing policy
- no listing fallback to PREVIEW/HIGHRES
- Archive calendar query carries tenant isolation and uses `public-editions` generation caching
- `/`, `/archive`, `/epaper/*`, `/edition/*`, `/search` route families retained
- service worker aligned with release VERSION
