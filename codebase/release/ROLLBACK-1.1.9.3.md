# Rollback — v1.1.9.3

Restore the v1.1.9.2 copies of:
- `app/Core/View.php`
- `public_html/assets/app.css`
- `public_html/service-worker.js`

Then purge Cloudways/Varnish/application cache and hard-refresh `/login`. No database rollback is required.
