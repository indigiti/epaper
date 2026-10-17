# Rollback — ePaper SaaS v1.1.7

No database migration exists in v1.1.7.

Rollback by restoring the v1.1.6.2 application files or the Cloudways backup taken immediately before deployment, then purge Varnish/browser/service-worker cache. Existing guest-created clips are ordinary `ep_clips` rows and do not require schema rollback.
