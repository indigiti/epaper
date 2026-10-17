# Rollback — v1.1.9.10

Restore the v1.1.9.9 versions of:
- `app/Core/View.php`
- `public_html/service-worker.js`

The new `public_html/assets/auth-v1.1.9.10.css` may be left unused or removed. Purge Varnish/application/browser caches after rollback.

No database rollback is required.
