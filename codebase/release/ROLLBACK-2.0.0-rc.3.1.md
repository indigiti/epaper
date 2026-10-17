# EP-DI v2.0.0-rc.3.1 — Rollback

RC.3.1 is presentation-only. To roll back, restore the application files from the RC.3 backup or redeploy the exact RC.3 package.

No database rollback is required because rc.3.1 contains no schema migration. No queue rollback is required. After restoring RC.3, hard-refresh once so the RC.3 Service Worker/assets become active.
