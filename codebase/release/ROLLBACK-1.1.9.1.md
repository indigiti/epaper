# Rollback — v1.1.9.1

Restore the three runtime files from the v1.1.9 backup:
- `app/Core/View.php`
- `public_html/assets/app.css`
- `public_html/service-worker.js`

Then purge Cloudways/Varnish/browser cache and hard-refresh `/login`.

No database rollback is required.
