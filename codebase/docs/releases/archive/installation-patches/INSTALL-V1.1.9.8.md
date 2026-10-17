# Install ePaper SaaS v1.1.9.8

Requires an existing v1.1.9.7 installation.

1. Take a Cloudways on-demand backup.
2. Extract the v1.1.9.8 hotfix into the existing outer `public_html`, overwriting matching files.
3. Keep the webroot as `public_html/public_html`.
4. Do not run `install.php`; there is no database migration.
5. No RabbitMQ or Supervisor restart is required.
6. Purge Varnish/application cache and hard-refresh `/login`.
7. Confirm `/assets/auth-v1.1.9.8.css?v=1.1.9.8` loads and the DOM includes `data-auth-version="1.1.9.8"`.
8. Refresh `/login` several times and verify the editorial quote rotates without changing the authentication flow.
