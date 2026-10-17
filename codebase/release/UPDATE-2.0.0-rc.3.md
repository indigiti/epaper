# EP-DI v2.0.0-rc.3 — Final Acceptance & UX/Defect Polish

## Upgrade from v2.0.0-rc.2

1. Take the normal Cloudways application/database backup.
2. Extract the RC.3 hotfix over the existing RC.2 application root.
3. **Do not run SQL.** RC.3 has no database migration.
4. **Do not run Upgrade Center repair** merely for RC.3.
5. No Supervisor restart is required; no long-running worker implementation/topology changed.
6. Hard-refresh once so `epdi-v2.0.0-rc.3` Service Worker becomes active.

## First verification

Open:

- `/admin/system/health`
- `/admin/system/release-audit`
- `/admin/editions`
- `/admin/processing`
- `/admin/errors`
- `/admin/performance`
- `/`
- `/archive`
- one publication landing page
- one published Reader edition
- Reader OS / encrypted Offline Vault

## What changed in Release Audit

The automated launch gate is intentionally stricter in RC.3. `ProductionReadiness::snapshot()` must report `READY`; a `DEGRADED` state now blocks automated stable approval.

Release Audit also includes a browser-local manual acceptance matrix. The checkboxes are stored only in local browser storage. They are an operator convenience and **not** an authoritative approval/audit record.

Use the matrix to complete the external/live items the package cannot prove:

- Cloudways backup and restore rehearsal;
- full publishing/reprocessing/scheduling flow;
- Reader desktop/tablet/mobile acceptance;
- encrypted offline download/airplane-mode/expiry/revocation/logout wipe;
- public Newsstand/Archive responsive and strict-thumbnail checks;
- Publisher Console CRUD/Archive/Restore/focus/touch/table checks;
- live SEO/sitemap/canonical/hreflang/structured-data validation;
- recovery, Error Center, queue/worker, cache, disk/log and scheduler catch-up validation.

## UX defect corrections

- `••• More` menus retain less-common actions even while their `<details>` popover is collapsed.
- Submitting forms now expose a busy state and disable the active submitter to reduce accidental duplicate operations.
- Publisher Console small operational text and mobile action targets have been raised to a practical floor.
- Publication cover cards use descriptive alt text and known THUMB dimensions when present.

## Promotion to v2.0.0 stable

Do not promote until:

- `/admin/system/health` reports `READY` for all required automated checks;
- `/admin/system/release-audit` reports automated PASS;
- all manual acceptance items have been completed on the deployed environment;
- the final backup/restore rehearsal is recorded;
- no unresolved critical/high defects remain.

## Rollback

RC.3 introduces no schema change. Restoring the exact released RC.2 application files is sufficient for application-code rollback. No database cleanup is required.
