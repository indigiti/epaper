# EP-DI v2.0.1.1 — Build Manifest

Baseline: exact user-supplied `EP-DI-v2.0.1(1).zip` / VERSION 2.0.1.

## Runtime changes
- `VERSION` → `2.0.1.1`
- `public_html/service-worker.js` — fixes Response clone timing and bumps Service Worker/cache asset version
- `public_html/assets/offline-vault.js` — intercepts `beforeinstallprompt` only when the custom Install button exists
- `bin/release-check.php` — hotfix guard alignment
- `tests-cumulative-check.php` — accepts maintenance versions such as `2.0.1.1`
- `tests/build-guard-v2.0.1.1.php` — targeted regression guard

## Documentation
- `CHANGELOG.md`
- `UPDATE-2.0.1.1.md`
- `ROLLBACK-2.0.1.1.md`

## Invariants
- No application route change
- No database/schema migration
- No Reader navigation change
- No Offline Vault encryption/storage change
- No RabbitMQ/Supervisor topology change
