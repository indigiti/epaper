# Build Manifest — EP-DI v2.0.0-alpha.2

## Baseline

- Immutable recovery baseline: `v1.1.9.11`
- Previous v2 engineering build: `v2.0.0-alpha.1`
- Current engineering build: `v2.0.0-alpha.2`
- Milestone: Runtime & Publishing Engine
- Package type: full engineering alpha
- Database prerequisite for upgrades: `database/upgrade-v2.0.0-alpha.1.sql`
- Database upgrade for this build: `database/upgrade-v2.0.0-alpha.2.sql`
- Supervisor restart after deployment: yes
- Production secrets/config included: no

## Added files versus alpha.1

- `app/Services/PublishingRuntime.php`
- `app/Services/ReaderNavigation.php`
- `app/Services/RuntimeCache.php`
- `database/upgrade-v2.0.0-alpha.2.sql`
- `tests/build-guard-v2.0.0-alpha.2.php`

## Primary changed files versus alpha.1

- `CHANGELOG.md`
- `VERSION`
- `app/App.php`
- `app/Core/RedisStore.php`
- `app/Core/View.php`
- `app/Services/InfrastructureConfig.php`
- `app/Services/OutboxPublisher.php`
- `bin/release-check.php`
- `database/schema.sql`
- `public_html/assets/app.css`
- `public_html/assets/reader.js`
- `public_html/offline.html`
- `public_html/service-worker.js`

## Runtime features

- Redis/filesystem/default compiled Reader Navigation data plane.
- Global/publication/city/language scoped fallback.
- Draft/Preview/Publish/immutable-version/Restore navigation workflow.
- Read-time scheduled navigation activation/expiry without MariaDB.
- Redis SCAN namespace operations; no blocking `KEYS` usage.
- Redis-first worker liveness with MariaDB recovery fallback.
- Short-lived publisher operational snapshot and Attention Center model.
- Cross-node cache invalidation through the transactional outbox.
- Optional runtime configuration for protected-media `X-Accel-Redirect`.
- Public SSR primary/focus/breaking navigation integration.

## Guard status

`php bin/release-check.php` → **PASS**.

Additional validation:

- PHP lint: PASS, 86 runtime/project PHP files.
- JavaScript syntax: PASS for app, CRUD, Reader, Reader Prefetch, Guest Clipping and Service Worker.
- Alpha.2 build guard: PASS.
- Tenant SQL guard: PASS, 156 tenant-owned application SQL statements checked.
- Reader parity: PASS.

## Operational caveats

- The migration has not been executed against the user's production database by this build process. Apply it under backup and maintenance/change control.
- Redis is an accelerator, not canonical persistence.
- `X-Accel-Redirect` must remain disabled until its Nginx internal route exists and has been tested.
- The legacy plaintext Reader-media cache remains only as temporary offline compatibility. Alpha.2 is **not** the encrypted Offline Vault release.
- Production performance targets still require production-like data and Cloudways load tests.
