# Rollback v2.0.1.4

1. Pause admin writes/scheduling. Stop ordinary EP-DI workers and every direct recovery process, including any optional epaper-direct-recovery Supervisor program. Verify PDF/OCR subprocesses have stopped.
2. Restore backed-up v2.0.1.3 files and remove new v2.0.1.4-only runtime files listed in release/CHANGED-FILES-2.0.1.4.json. Retain live configuration and storage. Remove the optional recovery Supervisor program if added.
3. No schema rollback is needed. Pending recovery.request.* entries will not be executed by v2.0.1.3. Do not broadly delete ep_system_state. In-flight jobs may need the v2.0.1.3 recovery workflow after workers restart.
4. Existing publishing intent may have resulted in a recovered edition being published. A code rollback does not undo that publication. Review those editions, or restore a paired database/storage backup if an exact state rollback is necessary; that discards changes since the backup.
5. Reload PHP/OPcache, restart the original worker groups and scheduler, and recheck admin/public reader/health. Stop the old direct processes before this restart to avoid mixed execution code.
