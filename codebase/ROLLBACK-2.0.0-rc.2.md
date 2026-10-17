# EP-DI v2.0.0-rc.2 — Rollback

RC.2 has **no database migration** and does not change RabbitMQ/Supervisor topology.

## Application rollback

1. Put the Publisher Console into a quiet maintenance window if possible.
2. Restore the exact released `v2.0.0-rc.1.2` files over the application root.
3. Do not drop or modify any database tables for an RC.2 rollback.
4. Hard-refresh the browser so the RC.1.2 Service Worker is restored.
5. Verify `/admin`, `/admin/processing`, `/`, `/archive`, a Reader edition and Reader OS.

## Scheduler

RC.2 adds a MariaDB advisory lock and richer scheduler heartbeat payload using the existing `ep_system_state` table. Rolling back code does not require database cleanup. The existing state rows are harmless historical telemetry.

## External recovery

Cloudways backup/restore remains an external operational control. Keep the RC.1.2 ZIP and a known-good database/application backup together until v2.0.0 stable is accepted.
