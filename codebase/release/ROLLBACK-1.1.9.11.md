# Rollback v1.1.9.11

Restore the three overwritten v1.1.9.10 runtime files from the pre-deployment Cloudways backup:

- `app/Core/View.php`
- `public_html/assets/auth-v1.1.9.10.css` / auth asset reference
- `public_html/service-worker.js`

Then purge Varnish/browser cache.
