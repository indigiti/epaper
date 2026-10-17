# EP-DI v2.0.0-rc.2 — Production Hardening & Launch Readiness

## Upgrade from v2.0.0-rc.1.2

1. Take the normal Cloudways application/database backup.
2. Extract the RC.2 hotfix over the existing RC.1.2 application root.
3. **Do not run SQL.** RC.2 has no database migration.
4. **Do not run Upgrade Center repair** merely for RC.2; Schema Doctor should remain aligned with the existing RC.1 schema.
5. No Supervisor restart is required by RC.2 because no long-running RabbitMQ worker implementation/topology changed.
6. The minute scheduler loads the new overlap-lock/heartbeat logic automatically on its next invocation.
7. Hard-refresh once so `epdi-v2.0.0-rc.2` Service Worker becomes active.

## First verification

Open:

- `/admin/system/health`
- `/admin/system/readiness.json`
- `/admin/processing`
- `/admin/performance`
- `/admin/errors`
- `/admin/storage`
- `/admin/system/release-audit`
- `/`
- `/archive`
- one published Reader edition
- Reader OS / encrypted offline edition

Optional server CLI:

```bash
php bin/readiness-check.php --tenant=<tenant-guid>
php bin/readiness-check.php --tenant=<tenant-guid> --json
```

The CLI intentionally defers the HTTPS-only web probe rather than reporting a false HTTP failure from a shell process.

## Readiness semantics

`READY` — required automated probe is healthy.  
`DEGRADED` — service is available but below preferred production state.  
`ACTION_REQUIRED` — required dependency can block reliable production operation.  
`REVIEW` — acceptance item cannot be proved safely by a local automated probe.

The page deliberately has **no synthetic numeric launch score**.

## Manual acceptance still required

Cloudways backup/restore state is external to EP-DI. RC.2 does not claim it has been verified. Before v2.0.0 stable:

- create an on-demand application/database backup;
- record timestamp/version;
- perform one documented restore rehearsal to a safe target or staging process;
- verify database schema, original PDFs, generated assets, configuration and Supervisor processes;
- complete desktop/laptop/tablet/mobile and offline-device acceptance testing;
- validate live canonical/robots/sitemaps/structured-data output on the deployed domain.

## Rollback

RC.2 introduces no schema change. Restoring the RC.1.2 application package is sufficient for application-code rollback; preserve normal database/media backups as operational safeguards.
