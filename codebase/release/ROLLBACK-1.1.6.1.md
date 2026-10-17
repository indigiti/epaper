# Rollback — ePaper SaaS v1.1.6.1

No database migration exists in this hotfix.

Rollback by restoring the five changed runtime files from v1.1.6, then purge Varnish/application cache and hard-refresh so the v1.1.6 service-worker cache is restored.
