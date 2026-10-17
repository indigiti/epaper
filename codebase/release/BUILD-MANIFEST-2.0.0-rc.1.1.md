# Build Manifest — EP-DI v2.0.0-rc.1.1

**Release:** Publisher Operations Console UX
**Baseline:** exact released v2.0.0-rc.1
**Database migration:** none

## Functional guardrails

- All RC.1 admin action routes retained.
- No database schema changes.
- No RabbitMQ/Supervisor topology changes.
- No Reader, encrypted Offline Vault or semantic SEO business-logic changes.
- `/admin/` is normalized to the existing `/admin` handler only.

## Principal changed files

- `VERSION`
- `app/App.php`
- `app/Core/View.php`
- `public_html/assets/admin-beta.css`
- `public_html/assets/crud.js`
- `public_html/service-worker.js`
- `public_html/offline.html`
- `public_html/offline-library.html`
- `public_html/offline-reader.html`
- `tests/build-guard-v2.0.0-rc.1.1.php`
- `tests/admin-console-ux-static.php`
- `bin/release-check.php`
- `ADMIN-UX-AUDIT-2.0.0-rc.1.1.md`
- `UPDATE-2.0.0-rc.1.1.md`
- `ROLLBACK-2.0.0-rc.1.1.md`
- `V2-BLUEPRINT.md`
- `CHANGELOG.md`

## UI system

Reusable tokens/components: compact application shell, typography scale, neutral surfaces, semantic status pills, icon-action cluster, archive/restore actions, tables, forms, drawers, overflow menus, focus states, reduced motion and responsive Editions composition.


## Verification gates

- Cumulative `php bin/release-check.php` must pass.
- All 35 pre-existing direct `/admin` dispatch paths are preserved; RC.1.1 adds only the exact `/admin/` compatibility normalization.
- All 21 regex-dispatched admin action route patterns are preserved unchanged.
- `database/schema.sql` and `database/upgrade-v2.0.0-rc.1.sql` are byte-identical to the released RC.1 baseline.
- PHP lint, JavaScript syntax, CSS parse, tenant-query guard, Reader parity and Offline Vault regression are release gates.
