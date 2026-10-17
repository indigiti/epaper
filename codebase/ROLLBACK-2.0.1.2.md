# EP-DI v2.0.1.2 — Rollback

Rollback target: exact v2.0.1.1 package.

- No database rollback is required because v2.0.1.2 has no schema migration.
- Restore the v2.0.1.1 application files or redeploy `EP-DI-v2.0.1.1.zip`.
- Hard-refresh once so the v2.0.1.1 service worker is active again.
- Existing `ep_system_state` rows such as `scheduler.last_cron`, `scheduler.history` and `readiness.redis_baseline` are harmless telemetry and may remain; v2.0.1.1 ignores them.
- Verify Processing, Infrastructure, System Health, Reader and Offline Vault after rollback.
