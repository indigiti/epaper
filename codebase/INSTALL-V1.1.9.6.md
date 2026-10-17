# Install v1.1.9.6

Requires ePaper SaaS v1.1.9.5.

1. Take a Cloudways on-demand backup.
2. Extract the hotfix into the existing outer `public_html`, overwriting matching files.
3. Keep the application Webroot as `public_html/public_html`.
4. Do not run `install.php`; there is no database migration.
5. Purge Varnish/application cache and hard-refresh `/login?return=%2Fadmin`.
6. In browser DevTools, confirm the auth root contains `data-auth-version="1.1.9.6"` and that `/assets/auth-v1.1.9.6.css?v=1.1.9.6` returns HTTP 200.
7. Test a real email/password/MFA sign-in and the `return=/admin` redirect.

No RabbitMQ or Supervisor restart is required.
