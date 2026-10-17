# Rollback — ePaper SaaS v1.1.9

v1.1.9 is a presentation-layer authentication hotfix with no database migration.

To roll back:
1. Restore `app/Core/View.php`, `public_html/assets/app.css`, `public_html/service-worker.js`, `VERSION`, `manifest.json`, and `bin/release-check.php` from the v1.1.8 backup/package.
2. Purge Cloudways/Varnish cache.
3. Hard-refresh `/login` so service-worker v1.1.8 becomes authoritative again.

No database rollback and no RabbitMQ/Supervisor action is required.
