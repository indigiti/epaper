# EP-DI v2.0.0-rc.3 — Rollback

RC.3 has **no database migration** and does not change RabbitMQ/Supervisor topology.

## Application rollback

1. Use a quiet maintenance window if practical.
2. Restore the exact released `v2.0.0-rc.2` files over the application root.
3. Do not drop or alter database tables for an RC.3 rollback.
4. Hard-refresh so the RC.2 Service Worker becomes active again.
5. Verify `/admin`, `/admin/editions`, `/admin/processing`, `/admin/system/health`, `/`, `/archive`, a Reader edition and Reader OS.

## Browser-local acceptance checklist

The RC.3 manual acceptance checklist is stored in browser localStorage under a versioned key. Rolling back application files does not alter server data. Any browser-local RC.3 checklist state may be ignored or cleared manually after rollback.

## Recovery safeguards

Keep the RC.2 ZIP, RC.3 ZIP and a known-good Cloudways backup together until v2.0.0 stable is formally accepted.
