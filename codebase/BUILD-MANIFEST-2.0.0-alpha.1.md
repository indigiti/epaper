# Build Manifest — EP-DI v2.0.0-alpha.1

## Baseline

- Recovery baseline: `v1.1.9.11`
- Current engineering build: `v2.0.0-alpha.1`
- Package type: full architecture alpha
- Database upgrade: `database/upgrade-v2.0.0-alpha.1.sql`
- Supervisor restart required after deployment testing: yes
- Production secrets/config included: no

## Primary changed runtime families

- Build/version: `app/Core/Build.php`, `app/Core/View.php`, `VERSION`
- Redis/cache: `app/Core/RedisStore.php`, `app/Services/TenantData.php`, `app/App.php`
- Media: `app/Services/Media.php`
- Workers: `bin/rabbit-worker.php`, `bin/outbox-publisher.php`, `SUPERVISOR.conf.example`
- Queue config: `bin/configure-rabbitmq.php`, `config/config.example.php`, `public_html/install.php`
- Reader: `public_html/assets/reader.js`, `public_html/assets/app.js`
- PWA: `public_html/service-worker.js`, `public_html/manifest.webmanifest`, `public_html/offline.html`
- Database: `database/schema.sql`, `database/upgrade-v2.0.0-alpha.1.sql`
- Release validation: `tests/build-guard-v2.0.0-alpha.1.php`, `bin/release-check.php`

## Guard status

`php bin/release-check.php` → PASS.

Additional static validation:

- PHP lint: PASS, 81 PHP files.
- JS syntax: PASS for modified/runtime JS family.
- Tenant SQL guard: PASS, 207 tenant-owned SQL statements.
- Reader parity: PASS.

## Deliberate compatibility hold

The current plaintext Reader-media/offline compatibility path is retained only until the encrypted Offline Vault replaces it. The package must not be represented as providing encrypted offline edition storage yet.
