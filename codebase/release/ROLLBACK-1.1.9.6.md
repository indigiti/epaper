# Rollback — v1.1.9.6

Restore the three runtime files from the v1.1.9.5 deployment/backup:

- `app/Core/View.php`
- `public_html/service-worker.js`

The new `public_html/assets/auth-v1.1.9.6.css` may be left unused or removed. Purge Varnish/browser cache after rollback.
