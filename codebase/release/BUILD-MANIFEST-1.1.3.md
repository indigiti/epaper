# Build Manifest — ePaper SaaS v1.1.3

Release: **TESTED**
Baseline: ePaper SaaS v1.1.2 + v1.1.2.1 auth/CSRF fix + v1.1.2.2 Reader viewport consolidation
City behavioral baseline: City ePaper v2.2.9.1

## Changed runtime files
- `app/App.php` — Reader PREVIEW/HIGHRES state, Pages/Data controls, auth no-store retention.
- `app/Core/View.php` — Data/network icon and v1.1.3 asset versions.
- `app/Core/Auth.php` — cumulative v1.1.2.1 CSRF/session rotation retained.
- `app/Core/Http.php` — cumulative private/no-store + CSRF helpers retained.
- `app/Services/AnalyticsCatalog.php` — registered `reader_quality_mode`.
- `public_html/assets/reader.css` — fixed viewport, independent scroll zones, Pages toggle, quality menu.
- `public_html/assets/reader.js` — Pages state + Auto/Low/High data-quality policy.
- `public_html/service-worker.js` — v1.1.3 cache alignment.
- `VERSION`, `manifest.json`, release manifests/docs/tests.

## Explicitly unchanged
- Database schema
- RabbitMQ topology
- Supervisor commands/counts
- Transactional Outbox
- Redis configuration
- Digiti Platform integration
- Tenant isolation model
- Processing pipeline
- Media token route
- Deep Zoom API route family
- Clip API/business logic
