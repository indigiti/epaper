# EP-DI v2.0.1.2 — Build Manifest

Release: **Web Recovery Center & Readiness Accuracy**  
Baseline: exact `EP-DI-v2.0.1.1.zip`

## Functional scope

- Add infrastructure-admin Web Recovery Center at `/admin/system/recovery`.
- Add Recovery JSON endpoint plus four POST-only recovery actions: scheduler run, safe outbox reconcile, missing-dispatch repair, Redis telemetry baseline.
- Treat `PROCESSED` `QUEUE_PUBLISH` as a confirmed RabbitMQ dispatch receipt.
- Replace broad queued-job redispatch with receipt-aware recovery.
- Add failed-outbox classifications: terminal, orphan, superseded, probe, publisher-confirm recoverable, invalid payload, unknown active.
- Unknown / ambiguous failed transports remain unchanged.
- Share one `PublishingScheduler::runOnce()` engine between cron and web; preserve advisory lock and add recurring-cron heartbeat/history.
- Separate Redis server memory/cumulative eviction counters from selected DB / EP-DI namespace metrics.
- Change processing readiness from original creation age to stale RUNNING activity + recent failure evidence.
- Route Processing/System Health recovery actions to Web Recovery Center.

## Security / operational boundaries

- Recovery page and writes require canonical-host `Auth::requireInfrastructureAdmin()` (`OWNER` / `ADMIN`).
- Mutating reconciliation actions require POST + CSRF + explicit confirmation + rate limits + audit events.
- Scheduler web action does not shell out; web and CLI call the same PHP service.
- No `FLUSHALL`, `CONFIG SET`, `supervisorctl`, daemon fork or server-wide destructive Redis action is exposed.
- Safe reconcile does not retry unknown failures.

## Runtime files changed

- `VERSION`
- `app/App.php`
- `app/Core/Auth.php`
- `app/Core/RedisStore.php`
- `app/Core/View.php`
- `app/Services/ProductionReadiness.php`
- `app/Services/QueueService.php`
- `bin/queue-diagnostic.php`
- `bin/release-check.php`
- `bin/scheduler.php`
- `public_html/assets/admin-beta.css`
- `public_html/service-worker.js`

## Runtime files added

- `app/Services/PublishingScheduler.php`
- `app/Services/RecoveryCenter.php`

## Test / guard changes

- `tests/build-guard-v2.0.1.2.php` added.
- `tests/web-recovery-static.php` added.
- scheduler static guards now inspect the shared scheduler service.
- tenant SQL guard documents `PublishingScheduler` as an intentional platform-wide operational service.

## Verification

- Cumulative release check: PASS.
- v2.0.1.2 Web Recovery guards: PASS.
- PHP syntax: 133 application/bin/public/test PHP files PASS.
- JavaScript / Service Worker syntax: 8 files PASS.
- CSS: 16 files / 2,138 rules / 0 parser errors.
- Tenant SQL guard: 215 statements PASS.
- Offline Vault crypto interoperability: `ECDH_WRAP_MATCH true 32`.
- Database directory: byte-identical to v2.0.1.1.
- Route delta: +6 infrastructure-admin recovery routes; 0 removed; parameterized routes unchanged.

## Database / workers

- No `database/upgrade-v2.0.1.2.sql`.
- No schema change.
- No RabbitMQ exchange/queue/lane change.
- No Supervisor worker-count/command topology change.
- No Reader, PWA entitlement, media policy or encrypted Offline Vault workflow change.
