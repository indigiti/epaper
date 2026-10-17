# EP-DI v2.0.0 — Rollback

Stable v2.0.0 has no database migration and no business-logic delta from v2.0.0-rc.3.1.1.

## Preferred rollback

1. Put the application into the same operational posture used for normal deployment changes.
2. Restore the exact v2.0.0-rc.3.1.1 application package, or restore the pre-promotion Cloudways application backup.
3. Do not roll back MariaDB solely for this stable promotion; the database schema is unchanged.
4. A Supervisor restart is not required solely because of this promotion/rollback, but may be performed if your operational policy requires it.
5. Hard-refresh browser clients once so the rc.3.1.1 service-worker cache namespace reactivates.
6. Verify `/admin/system/health`, Home, Archive, a publication rail, Reader, Processing and Offline Vault.

If the incident is unrelated to the stable promotion, use the normal production recovery procedure rather than assuming a release rollback will correct it.
