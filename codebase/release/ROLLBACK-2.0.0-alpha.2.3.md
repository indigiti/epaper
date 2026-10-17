# Rollback — EP-DI v2.0.0-alpha.2.3

Alpha.2.3 introduces no database migration.

Rollback to alpha.2.2 is therefore file-only:

1. Stop/restart Supervisor worker programs as appropriate for the deployment window.
2. Restore the alpha.2.2 application files from the previous verified package/backup.
3. Do **not** roll back the alpha.2 Reader Navigation schema; it remains required by alpha.2.2.
4. Clear application/service-worker cache as needed.
5. Restart all Supervisor worker lanes so long-running PHP processes load the restored release.
6. Verify `/admin/system/upgrade`, `/admin/system/infrastructure`, `/admin/system/health`, `/health/queue` and a test edition pipeline.

Because `storage/logs` is application-owned and non-destructive, it may remain present after rollback.
