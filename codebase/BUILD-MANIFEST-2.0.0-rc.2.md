# Build Manifest — EP-DI v2.0.0-rc.2

Release: **Production Hardening & Launch Readiness**  
Baseline: **EP-DI v2.0.0-rc.1.2**

## Functional scope
- Evidence-based Production Readiness Center
- Stuck-job, scheduler, outbox and worker health signals
- Privacy-safe current-request database observability
- High-volume endpoint rate limiting
- Scheduler overlap/catch-up hardening
- Error/request/job correlation
- Disk/log/temp-file pressure and backup/restore acceptance guidance
- Current-v2 Release Audit

## Changed runtime files
- `VERSION`
- `app/App.php`
- `app/Core/View.php`
- `app/Core/Store.php`
- `app/Services/ErrorCenter.php`
- `app/Services/PublishingRuntime.php`
- `bin/scheduler.php`
- `bin/release-check.php`
- `config/config.example.php`
- `public_html/assets/admin-beta.css`
- `public_html/service-worker.js`
- `public_html/offline.html`
- `public_html/offline-library.html`
- `public_html/offline-reader.html`

## Added runtime files
- `app/Services/ProductionReadiness.php`
- `bin/readiness-check.php`

## Added release tests
- `tests/build-guard-v2.0.0-rc.2.php`
- `tests/production-readiness-static.php`
- `tests/security-hardening-static.php`

## Documentation
- `UPDATE-2.0.0-rc.2.md`
- `BUILD-MANIFEST-2.0.0-rc.2.md`
- `ROLLBACK-2.0.0-rc.2.md`
- `V2-BLUEPRINT.md`
- `CHANGELOG.md`

## Database
No schema change. No `database/upgrade-v2.0.0-rc.2.sql` exists.

The scheduler writes richer JSON heartbeat data into the existing `ep_system_state` table; this is telemetry, not a schema migration.

## Worker topology
No RabbitMQ/Supervisor worker implementation or lane-count change. A Supervisor restart is therefore not required solely for RC.2.

## Preserved systems
- RC.1.2 Public Newsstand & Archive UX and strict THUMB listing policy
- RC.1.1 Publisher Operations Console design baseline
- RC.1 Semantic News SEO / Editorial Trust schema
- beta.2.1 Multi-Layer Cache & Delivery Engine
- beta.2 Encrypted Offline Vault
- beta.1.2 Processing Control / Recovery Profiles
- alpha.2.3 RabbitMQ/Supervisor topology

## Hardening notes
- Production readiness uses `READY`, `DEGRADED`, `ACTION_REQUIRED` and `REVIEW`; no synthetic numeric launch score is generated.
- Cloudways backup/restore and cross-device/browser acceptance remain manual `REVIEW` items because local code cannot prove them.
- Temp-file readiness scans are bounded so diagnostics cannot traverse an unbounded edition library.
- Slow-query samples store only timing, SQL verb and a normalized SQL signature hash; parameters/customer data are not captured.
- Redis is the shared production throttling backend with existing session fallback if unavailable.
