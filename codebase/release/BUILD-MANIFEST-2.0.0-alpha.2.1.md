# Build Manifest — EP-DI v2.0.0-alpha.2.1

## Release identity
- Version: `2.0.0-alpha.2.1`
- Parent: `2.0.0-alpha.2`
- Type: cumulative engineering hotfix
- Purpose: Web Upgrade & Schema Doctor
- Recovery baseline remains: `v1.1.9.11`

## Added
- `app/Services/SchemaDoctor.php`
- `public_html/upgrade-check.php`
- `bin/schema-check.php`
- `tests/build-guard-v2.0.0-alpha.2.1.php`
- `UPDATE-2.0.0-alpha.2.1.md`

## Changed
- `app/App.php` — protected Upgrade Center and dynamic Attention Center links.
- `app/Core/View.php` — Upgrade Center navigation for canonical infrastructure admins.
- `app/Services/PublishingRuntime.php` — cached schema drift warning.
- `public_html/service-worker.js` — cache/build version alignment.
- `VERSION`, `CHANGELOG.md`, `V2-BLUEPRINT.md`, release guard wiring.

## Database
No new application schema is introduced by alpha.2.1 itself. The web doctor detects/applies the already-required:
- `database/upgrade-v2.0.0-alpha.1.sql` effect (settings scope index), and
- `database/upgrade-v2.0.0-alpha.2.sql` Reader Navigation schema.

## Security boundary
Automatic repair is limited to known v2 migration deltas. Missing base schema blocks repair.

## Validation
- PHP lint across application/bin/public/test PHP files.
- JavaScript syntax checks for app/Reader/service-worker assets.
- `tests/build-guard-v2.0.0-alpha.2.1.php`.
- `tests/schema-doctor-static.php`.
- tenant SQL isolation guard.
- Reader parity regression guard.
- cumulative `php bin/release-check.php`.
