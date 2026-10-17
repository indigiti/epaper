# Install v1.1.9.10

Requires ePaper SaaS v1.1.9.9.

1. Take a Cloudways on-demand backup.
2. Extract the hotfix into the existing outer `public_html`, overwriting matching files.
3. Keep the webroot as `public_html/public_html`.
4. Do not run `install.php`; there is no DB migration.
5. Purge Cloudways/Varnish/application cache.
6. Unregister/refresh the old service worker if the browser keeps an old auth asset, then hard-refresh `/login`.
7. Confirm `/login` HTML contains `data-auth-version="1.1.9.10"` and loads `/assets/auth-v1.1.9.10.css?v=1.1.9.10`.
8. Verify the quote starts immediately below the security note with no large blank zone.

No RabbitMQ or Supervisor restart is required.
