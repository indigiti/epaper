# EP-DI v2.0.0 — Production Deployment Runbook

## Supported promotion path

Current baseline: `v2.0.0-rc.3.1.1`.

Preferred: apply `EP-DI-v2.0.0-STABLE-PROMOTION-HOTFIX.zip` over the exact rc.3.1.1 installation. The cumulative package is suitable for a clean/cumulative deployment.

## Pre-deployment gate

1. Confirm the currently deployed application reports `2.0.0-rc.3.1.1`.
2. Take an on-demand Cloudways application + MariaDB backup.
3. Record the backup timestamp/reference.
4. Open `/admin/system/health`; do not promote while required checks are `ACTION REQUIRED` or `DEGRADED`.
5. Review `/admin/system/release-audit` and unresolved incidents/jobs.

## Deploy

1. Extract the stable promotion hotfix over the current application root.
2. Do **not** run SQL. There is no `database/upgrade-v2.0.0.sql` migration.
3. Do **not** run Upgrade Center repair for this promotion.
4. No Supervisor restart is required: worker/runtime business logic is unchanged from rc.3.1.1.
5. Allow the next normal scheduler invocation to run.
6. Hard-refresh browser clients once so the `epdi-v2.0.0` service worker and stable asset cache keys activate.

## Immediate smoke verification

- `/health/live`
- `/health/ready`
- `/`
- `/archive`
- `/epaper/civic-mirror`
- one additional publication landing page
- one published `/edition/{slug}/page/1`
- `/admin`
- `/admin/editions`
- `/admin/processing`
- `/admin/system/health`
- `/admin/system/release-audit`
- Reader OS account/library surface
- one already-downloaded encrypted Offline Vault edition

## Success condition

- VERSION reports `2.0.0`.
- Production Readiness is `READY`.
- No new critical/high application incident.
- Processing and scheduler heartbeats remain current.
- Publication rails remain horizontally swipeable with non-stretched covers.
- Reader and Offline Vault regressions are absent.

If any critical/high regression appears, stop promotion and follow `ROLLBACK-2.0.0.md`.
