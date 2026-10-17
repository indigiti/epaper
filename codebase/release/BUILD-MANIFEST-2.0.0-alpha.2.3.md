# EP-DI v2.0.0-alpha.2.3 — Build Manifest

## Release purpose

Supervisor Bootstrap & Worker Doctor after successful alpha.2 schema repair.

## Added

- `app/Services/SupervisorRuntime.php`
- `bin/supervisor-preflight.php`
- `tests/build-guard-v2.0.0-alpha.2.3.php`
- `tests/supervisor-runtime-static.php`
- `storage/logs/.gitkeep`
- `/admin/system/infrastructure/supervisor`
- `/admin/system/infrastructure/supervisor.conf`

## Changed

- `app/Services/InfrastructureConfig.php` — Redis + MariaDB merged worker liveness.
- `app/Services/PublishingRuntime.php` — worker Attention Center route targets Worker Doctor.
- `app/App.php` — Supervisor setup/download UI and Infrastructure CTA.
- `bin/outbox-publisher.php` — throttled heartbeat writes + versioned heartbeat payload.
- `bin/rabbit-worker.php` — throttled heartbeat writes.
- `SUPERVISOR.conf.example` and `RABBITMQ-SUPERVISOR-SETUP.md` — generated-config workflow.
- service-worker/offline/Reader build identity to alpha.2.3.

## Database

No migration.

## Release guards

- PHP syntax validation
- JS/service-worker syntax validation
- v2.0.0-alpha.2.3 build guard
- Supervisor runtime static guard
- Schema Doctor static guard
- Audit UUID safety guard
- tenant SQL isolation guard
- Reader regression/parity guard
- cumulative `bin/release-check.php`
