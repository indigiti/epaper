# Rollback — ePaper SaaS v1.1.6

There is no database migration.

Restore the v1.1.5.1 versions of `app/App.php`, `app/Core/View.php`, `public_html/assets/app.css`, `public_html/service-worker.js`, `VERSION` and `manifest.json`, then purge Varnish/browser/service-worker caches.

No generated social images need cleanup because v1.1.6 creates none.
