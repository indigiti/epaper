# EP-DI v2.0.0 — Production Stable

EP-DI v2.0.0 promotes the exact functional baseline of v2.0.0-rc.3.1.1 to the stable release line.

## Stable release contract

- No database migration.
- No route/API change.
- No permission/RBAC change.
- No processing, reprocessing, publishing or scheduling behavior change.
- No Reader OS or encrypted Offline Vault behavior change.
- No semantic-news/SEO behavior change.
- No Publisher Console workflow change.
- Public Newsstand behavior confirmed in rc.3.1.1 is retained: horizontal publication rails, non-stretched covers, strict THUMB delivery on listing surfaces.

The promotion delta is release identity and delivery metadata only: VERSION, PWA/service-worker cache keys, release guards, checksums, stable documentation, route/schema inventories and acceptance records.

## Frozen functional baseline

- `app/App.php` SHA-256: `b13ed088f69d905bdb7dabb132a27967c9639cc2789deeb2ca08fb5962b15886`
- `public_html/assets/app.css` SHA-256: `fb6d401c5e0c6aa67ae8a0475713d28c19fd643c4f793e3b25142a6c066863e8`
- `database/` is byte-identical to rc.3.1.1.
- `SUPERVISOR.conf.example` SHA-256: `eda34017ddf7f9226f3c52d8208692046c1a265cb3fc9746f9cb0eee2dcc5930`

## Deployment approval

The package is release-gate verified. Before production promotion, the operator must confirm `/admin/system/health` is `READY`, take a current backup, and complete the environment-specific acceptance items in `PRODUCTION-ACCEPTANCE-REPORT-2.0.0.md` / `BACKUP-RESTORE-CHECKLIST-2.0.0.md`.
