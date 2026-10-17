# EP-DI v2.0.1.2 — Web Recovery Center & Readiness Accuracy

## Upgrade from v2.0.1.1

1. Take the normal Cloudways application + MariaDB backup.
2. Extract `EP-DI-v2.0.1.2-WEB-RECOVERY-HOTFIX.zip` over the exact v2.0.1.1 application.
3. **Do not run SQL.** No v2.0.1.2 migration exists.
4. **Do not run Upgrade Center repair** merely for this update.
5. No Supervisor topology restart is required; queue lanes/worker commands are unchanged.
6. Hard-refresh once so `epdi-v2.0.1.2` activates.

## First web recovery workflow

Open `/admin/system/recovery` as an OWNER or ADMIN on the canonical hostname. The page is deliberately read-mostly until you confirm a specific repair.

Recommended order for the queue state you reported:

1. Review **Dispatch confirmed / Missing receipt / Failed dispatch**.
2. Review failed-outbox classification. Historical terminal/orphan/probe/superseded failures can be closed safely; unknown active failures stay untouched.
3. Use **Apply safe reconcile**.
4. Use **Repair missing dispatches** only if the page reports one or more active jobs with no `QUEUE_PUBLISH` history.
5. Use **Run scheduler now** to verify the scheduler engine. Then configure/verify the displayed one-minute Cloudways cron; the manual run does not mark recurring cron healthy.
6. Capture a Redis eviction baseline. Readiness will then show the eviction delta after that baseline instead of treating the historical cumulative counter as a permanent failure.
7. Refresh `/admin/system/health`.

## Safety changes

- `PROCESSED` queue-publish rows are successful broker dispatch receipts.
- Confirmed dispatches are preserved; they are not blindly redispatched because a DB job is still `QUEUED`.
- Unknown/invalid active failures are never auto-repaired.
- Web recovery requires canonical-host infrastructure admin (`OWNER`/`ADMIN`), CSRF, rate limiting and audit logging.
- No `shell_exec()` or `supervisorctl` is used to run the scheduler.
- No Redis `FLUSHALL`, `CONFIG SET` or global destructive action is exposed.

## Readiness changes

- Redis: server memory/historical evictions are shown separately from selected DB/EP-DI namespace keys. Historical evictions alone do not force DEGRADED.
- Processing: stale RUNNING heartbeat and recent failures drive status; original job creation age is informational.
- Outbox: actionable failures, historical/closeable failures, confirmed dispatches and missing receipts are distinguished.
- Scheduler: recurring cron heartbeat is separate from any manual/web scheduler run.
