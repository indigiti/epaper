# Build Manifest — EP-DI v2.0.0 Production Stable

Baseline: exact `EP-DI-v2.0.0-rc.3.1.1.zip`.

## Functional delta

None. `app/App.php`, application services, Reader assets, admin UX CSS/JS and database schema remain functionally identical to rc.3.1.1. Public application CSS is byte-identical.

## Stable promotion changes

- `VERSION` → `2.0.0`
- `public_html/service-worker.js` stable cache namespace + asset query keys
- `public_html/offline.html` stable release label
- `public_html/offline-library.html` stable static asset/runtime version
- `public_html/offline-reader.html` stable static asset/runtime version
- `bin/release-check.php` uses `tests/build-guard-v2.0.0.php`
- `tests/build-guard-v2.0.0.php` added
- `tests-cumulative-check.php` accepts the stable `2.0.0` version format in addition to historical prerelease formats
- stable release documentation/inventories/checklists added
- `CHANGELOG.md`, `V2-BLUEPRINT.md`, `README-FIRST.txt` updated for stable promotion

## Frozen hashes

- `app/App.php`: `b13ed088f69d905bdb7dabb132a27967c9639cc2789deeb2ca08fb5962b15886`
- `public_html/assets/app.css`: `fb6d401c5e0c6aa67ae8a0475713d28c19fd643c4f793e3b25142a6c066863e8`
- `SUPERVISOR.conf.example`: `eda34017ddf7f9226f3c52d8208692046c1a265cb3fc9746f9cb0eee2dcc5930`

## Database

No stable migration. See `DATABASE-SCHEMA-MANIFEST-2.0.0.txt`.

## Rollback

Application-only rollback to exact rc.3.1.1 is sufficient for a stable-promotion regression; no database rollback is required.
