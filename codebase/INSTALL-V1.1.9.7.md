# Install ePaper SaaS v1.1.9.7

Requires **v1.1.9.6**.

1. Take a Cloudways on-demand backup.
2. Extract the v1.1.9.7 hotfix into the existing outer `public_html`, overwriting matching files.
3. Keep the application Webroot as `public_html/public_html`.
4. Do not run `install.php`; there is no database migration.
5. Purge Varnish/application cache and hard-refresh `/login?return=%2Fadmin`.
6. Confirm the page DOM contains `data-auth-version="1.1.9.7"` and Network loads `/assets/auth-v1.1.9.7.css?v=1.1.9.7`.
7. Test login at normal desktop size, browser zoom 67/80/100/125/150%, and one mobile viewport.

No RabbitMQ or Supervisor restart is required.
