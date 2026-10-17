# Install v1.1.9

1. Take a Cloudways on-demand backup.
2. Extract the v1.1.9 hotfix into the existing outer `public_html` over v1.1.8.
3. Keep Webroot `public_html/public_html`.
4. Do not run `install.php`; there is no database migration.
5. No RabbitMQ/Supervisor restart is required.
6. Purge Cloudways/Varnish cache and hard-refresh `/login`.
7. Test the configured Auth theme at desktop and mobile widths.
8. Confirm sign-in still posts and completes on the current ePaper domain and no Platform UI/login is exposed.
