# EP-DI v2.0.0-beta.1.2 Rollback

Beta.1.2 has **no database migration**. Rollback is therefore file-level.

1. Disable Automatic rescue in `/admin/settings/processing` before rollback if it was enabled.
2. Allow active processing pipelines to complete or pause processing operations.
3. Restore the `2.0.0-beta.1.1` application files.
4. Restart Supervisor workers so long-running PHP processes load the restored code.
5. Hard-refresh the browser/service worker.
6. Verify `/admin/system/health`, `/admin/processing`, `/admin/editions` and `/admin/settings/processing`.

The additional `processing.*` settings stored in `ep_settings` are harmless if beta.1.1 files are restored; beta.1.1 simply does not read the new keys. They do not need to be deleted.
