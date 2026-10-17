# Install v1.1.9.3 hotfix

Requires ePaper SaaS v1.1.9.2.

1. Take a Cloudways on-demand backup.
2. Extract the hotfix into the existing outer `public_html`, overwriting matching files.
3. Keep the application Webroot as `public_html/public_html`.
4. Do not run `install.php`; there is no database migration.
5. No RabbitMQ or Supervisor restart is required.
6. Purge Cloudways/Varnish/application cache and hard-refresh `/login`.
7. Verify `/login?return=%2Fadmin` at desktop and mobile widths.
