# Rollback — EP-DI v2.0.0-rc.1.1

RC.1.1 is an admin presentation release with no database migration.

1. Put the application in the same maintenance/deployment state used for normal file updates.
2. Restore the exact v2.0.0-rc.1 application files (or redeploy the verified RC.1 full package).
3. Hard refresh once so the RC.1 service worker/assets are active.
4. Verify `/admin`, `/admin/editions`, `/admin/processing`, `/admin/settings` and `/admin/system/health`.

Do not alter the RC.1 semantic-news tables; this release makes no schema changes.
