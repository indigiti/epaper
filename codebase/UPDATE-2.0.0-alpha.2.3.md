# EP-DI v2.0.0-alpha.2.3 — Supervisor Bootstrap & Worker Doctor

## Why this release exists

After the alpha.2 schema repair was confirmed successful, System Health still showed Supervisor workers `0 / 6` while MariaDB, Redis and RabbitMQ were healthy.

Alpha.2.3 focuses only on the runtime worker layer. It does **not** change the database schema.

## What changed

### 1. New web Supervisor Setup & Worker Doctor

Open:

```text
/admin/system/infrastructure/supervisor
```

The page is restricted to infrastructure admins and provides:

- live lane diagnosis for Outbox, Render, Assets, OCR and Finalize;
- heartbeat count vs expected process count;
- independent RabbitMQ consumer count for work queues;
- exact commands built from the actual deployed application path;
- credential-free downloadable `EP-DI-supervisor.conf`;
- web/CLI preflight checks;
- post-start verification instructions.

It deliberately does **not** execute `supervisorctl`, daemonize PHP or write operating-system Supervisor configuration.

### 2. Fixed heartbeat false-negative behavior

Alpha.2 returned immediately from the Redis branch whenever Redis was reachable. If Redis was reachable but had no current worker heartbeat keys, the recent MariaDB heartbeat mirror was never consulted.

Alpha.2.3 now merges both sources and deduplicates workers by worker identity:

```text
Redis expiring heartbeat keys
        +
recent MariaDB heartbeat mirror
        ↓
combined worker status
```

This avoids a false `0 / 6` when workers have a valid recent MariaDB heartbeat but Redis heartbeat visibility was interrupted by a transient Redis/prefix/config transition.

### 3. Broker-consumer diagnosis

For Render / Assets / OCR / Finalize, Worker Doctor cross-checks RabbitMQ consumer counts.

It can distinguish:

- `HEALTHY` — heartbeat and broker consumers meet configured capacity;
- `NOT_RUNNING` — neither worker heartbeat nor broker consumer is present;
- `HEARTBEAT_MISSING` — RabbitMQ sees the consumer, but the current application heartbeat is absent;
- `BROKER_CONSUMER_MISSING` — a heartbeat exists but the broker does not see enough consumers;
- `PARTIAL` — only part of configured concurrency is live.

This is much more actionable than the previous generic `Missing heartbeat` message.

### 4. `storage/logs` prerequisite fixed

The included Supervisor configuration writes stdout logs under:

```text
<APP_ROOT>/storage/logs/
```

Earlier packages did not guarantee that this directory was present in the ZIP. Supervisor generally cannot create a missing parent directory for its log file.

Alpha.2.3:

- ships `storage/logs/.gitkeep` so the directory exists after extraction;
- safely creates `storage/logs` from Worker Doctor if it is missing and the application-owned `storage` directory is writable;
- checks write access during preflight.

If your Supervisor jobs previously entered a failed/fatal state because the log directory was missing, reload/restart those jobs after applying alpha.2.3.

### 5. Lower Redis heartbeat churn

The idle outbox publisher previously refreshed its Redis heartbeat on almost every empty polling loop (roughly twice per second).

Alpha.2.3 throttles worker heartbeat writes:

- Redis heartbeat: at most once per ~10 seconds;
- expiry: 60 seconds;
- MariaDB recovery mirror: at most once per ~20 seconds.

This keeps failure detection fast while reducing unnecessary Redis writes.

## Database

**No database migration is required.**

Do not rerun `database/upgrade-v2.0.0-alpha.2.sql` if Upgrade Center already reports:

> Previous schema repair recorded

and the schema checks pass.

## Deployment

1. Take a Cloudways on-demand backup.
2. Upload the full alpha.2.3 package or the Supervisor hotfix over the current application root.
3. Ensure `app/`, `bin/`, `public_html/`, `storage/logs/` and `VERSION` are overwritten/created.
4. Open:

```text
/admin/system/infrastructure/supervisor
```

5. Confirm preflight requirements.
6. If `Storage logs` says it was created, restart/reload existing Supervisor programs.
7. For each `NOT_RUNNING` lane, create/start the exact command shown by Worker Doctor.
8. Wait at least 15 seconds.
9. Refresh Worker Doctor.
10. Target:

```text
Outbox    1 / 1
Render    1 / 1
Assets    2 / 2
OCR       1 / 1
Finalize  1 / 1
```

11. Open Infrastructure and run the end-to-end worker pipeline test.
12. Recheck `/admin/system/health`.

## SSH parity test

From the outer application root:

```bash
php bin/supervisor-preflight.php
```

Expected final line:

```text
READY
```

## Security

The generated Supervisor configuration contains application paths and process counts only. RabbitMQ/Redis passwords are not embedded.

Long-running process creation remains in the hosting/server control plane. This is intentional so a compromised PHP/FPM process cannot arbitrarily start privileged persistent daemons.
