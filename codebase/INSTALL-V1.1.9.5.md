# Install v1.1.9.5

Prerequisite: ePaper SaaS v1.1.9.4.

1. Take a Cloudways on-demand backup.
2. Extract the v1.1.9.5 hotfix into the existing outer `public_html`, overwriting matching files.
3. Keep the application webroot unchanged: `public_html/public_html`.
4. Do not run `install.php`; there is no database migration.
5. No RabbitMQ or Supervisor restart is required.
6. Purge Cloudways/Varnish application cache.
7. Open `/login?return=%2Fadmin` in a fresh tab and hard refresh once.
8. Verify desktop and mobile login, then perform a real sign-in/MFA test.

The new auth stylesheet has a unique path (`auth-v1.1.9.5.css`), so it does not depend on the browser replacing an older auth stylesheet at the same URL.
