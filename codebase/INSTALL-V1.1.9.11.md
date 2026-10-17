# Install v1.1.9.11

Requires ePaper SaaS v1.1.9.10.

1. Take a Cloudways on-demand backup.
2. Extract this ZIP into the existing outer `public_html`, overwriting matching files.
3. Keep the webroot unchanged: `public_html/public_html`.
4. Do not run the installer; there is no database migration.
5. Purge Cloudways/Varnish/application cache.
6. Hard-refresh `/login` or test in a fresh incognito window.
7. Confirm page source contains `data-auth-version="1.1.9.11"`.

No RabbitMQ or Supervisor restart is required.
