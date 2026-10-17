# Rollback — v1.1.6.2

No database migration is included.

1. Restore the Cloudways backup taken immediately before deployment or overlay the previous v1.1.6.1 files.
2. Purge Varnish/application cache.
3. Hard-refresh browser/service-worker assets.

Records intentionally deleted by a user through Clear All are business actions and are not recreated by a code rollback.
