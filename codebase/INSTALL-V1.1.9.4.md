# Install v1.1.9.4

Requires ePaper SaaS v1.1.9.3.

1. Take a Cloudways on-demand backup.
2. Extract this hotfix into the existing outer `public_html`, overwriting matching files.
3. Keep the webroot as `public_html/public_html`.
4. Do not run `install.php`; there is no migration.
5. Purge Cloudways/Varnish cache.
6. Hard-refresh `/login?return=%2Fadmin` once. The service-worker/cache version is `1.1.9.4`.
7. Verify desktop and mobile login, then perform one real sign-in.

No RabbitMQ or Supervisor restart is required.
