# Rollback — ePaper SaaS v1.1.4

There is no database migration in v1.1.4.

Preferred rollback:
1. Use the Cloudways on-demand backup taken immediately before deployment, or restore the previous v1.1.3 application files.
2. Keep the current database, Redis, RabbitMQ and Platform configuration unchanged.
3. Purge Varnish/application cache and hard-refresh browsers/service worker after restoring files.
4. Re-run `php bin/release-check.php` on the restored baseline.

Clippings created under v1.1.4 use the existing clipping tables/schema; rollback does not require schema reversal.
