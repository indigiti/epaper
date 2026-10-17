# Rollback EP-DI v2.0.1.3

1. Pause admin writes and scheduler; stop all EP-DI worker/publisher processes and verify their subprocesses have exited.
2. Prefer resolving pending cancellations and restoring any editions you want to retain in the active list through v2.0.1.3 before rollback. The previous UI does not understand the new Trash workflow. Do not directly mark a partially processed edition READY or PUBLISHED.
3. Restore the backed-up v2.0.1.2 application files, retaining live configuration and storage. Remove files listed as added in release/CHANGED-FILES-2.0.1.3.json when they are not in that backup. Do not delete customer media.
4. No schema rollback is necessary. For an exact state rollback, restore the paired MariaDB/application-storage snapshot; this discards changes since the backup and must be planned accordingly. Otherwise retain the database and review TRASHED/CANCELLED states before reverting; do not bulk-convert them to publishable states.
5. Reload PHP/OPcache, restart original Supervisor groups and scheduler, hard refresh and recheck public reader/admin/health.
