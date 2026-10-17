# Rollback — v1.1.0

v1.1.0 has no database schema migration. Rollback is file-level:

1. Stop/restart ePaper Supervisor workers during the file switch.
2. Restore the previous application files from the Cloudways on-demand backup or your prior cumulative package.
3. Keep the existing database, Redis and RabbitMQ data.
4. Purge Varnish/browser caches so older Reader assets/service worker are reloaded.
5. Verify `/health/ready`, `/health/platform`, `/health/queue` and one Reader route.

Do not delete queue/audit/error history as part of rollback.
