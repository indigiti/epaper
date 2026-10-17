# EP-DI v2.0.0-beta.1.1 Rollback

Direct application rollback baseline: **v2.0.0-beta.1**.

## Before deployment

Take an on-demand Cloudways application/database backup before running the beta.1.1 Schema Doctor repair.

## Preferred rollback

1. Stop new edition scheduling/reprocessing actions while rollback is in progress.
2. Restore the `v2.0.0-beta.1` application files (or the pre-deploy application backup).
3. Keep the beta.1.1 database additions in place unless there is a specific reason to remove them.
4. Restart/reload PHP and Supervisor workers if your deployment method requires it.
5. Verify `/admin`, `/admin/editions`, `/admin/processing`, `/admin/system/health` and the public Reader.

The beta.1.1 schema change is additive (`publish_mode`, `schedule_timezone` and two indexes). Beta.1 does not depend on those fields and should tolerate them remaining in MariaDB. Leaving them in place is the safer rollback because dropping columns could discard publishing-intent data created after beta.1.1 deployment.

## Strict schema rollback (not normally recommended)

Only after a database backup and only when no beta.1.1 scheduling data must be retained, an operator may manually remove the two beta.1.1 indexes and columns. This is intentionally not automated by the web Upgrade Center because it is destructive.

## Scheduled editions during rollback

Before restoring beta.1 files, review any editions with `publish_mode='SCHEDULED'` or `publish_mode='AUTO_READY'`. If scheduling must be suspended, cancel those schedules from beta.1.1 before file rollback. The beta.1 scheduler does not understand beta.1.1 publishing intent.
