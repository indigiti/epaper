# Build Manifest — EP-DI v2.0.0-alpha.2.2

- Version: `2.0.0-alpha.2.2`
- Release type: cumulative engineering hotfix
- Parent: `v2.0.0-alpha.2.1`
- Database migration: none

## Primary changed files

- `app/Services/Audit.php`
- `app/Services/SchemaDoctor.php`
- `app/Core/Http.php`
- `app/App.php`
- `VERSION`
- `public_html/service-worker.js`
- `public_html/offline.html`
- `tests/audit-uuid-safety.php`
- `tests/build-guard-v2.0.0-alpha.2.2.php`
- `bin/release-check.php`
- `UPDATE-2.0.0-alpha.2.2.md`

## Fixes

1. Prevent non-UUID audit resource references from being passed to `Uuid::toBinary()`.
2. Make schema-repair success/failure rendering independent from secondary audit transport failures.
3. Preserve non-UUID resource references in audit metadata.
4. Strictly validate incoming correlation GUIDs.
5. Surface the last successful schema-repair marker in Upgrade Center.

## Release guards

- PHP syntax validation
- JavaScript syntax validation
- v2.0.0-alpha.2.2 build guard
- Schema Doctor static guard
- Audit UUID safety guard
- Tenant SQL isolation guard
- Reader parity guard
