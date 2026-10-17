# Update to EP-DI v2.0.1.9

Base: **v2.0.1.8.1**

1. Take an application/database backup.
2. Overlay the v2.0.1.9 patch or deploy the full-source package.
3. No database migration is required.
4. Restart PHP-FPM/opcache if your deployment process does not invalidate it automatically.
5. Restart EP-DI Supervisor workers so all long-running processes load the new source.
6. Open `/admin/system/health` and `/admin/performance`.
7. Verify Service Worker alignment reports `epdi-v2.0.1.9`.
8. If protected media is configured for X-Accel, load a Reader page and confirm media responses show `X-EPDI-Media-Delivery: x-accel`.
9. Allow the Performance Center to collect a rolling sample window, then review route p95 and Web Vitals.

Rollback: restore the v2.0.1.8.1 application files. There is no v2.0.1.9 schema rollback because this release has no database migration.
