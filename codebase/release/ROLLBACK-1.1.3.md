# Rollback — v1.1.3

1. Restore the Cloudways on-demand backup taken immediately before deployment, or overwrite the v1.1.3 changed files with the previous verified build.
2. Purge Varnish/application cache.
3. Hard-refresh the Reader/service worker.

No database rollback is required because v1.1.3 has no schema migration.
