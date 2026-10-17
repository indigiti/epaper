# Install v1.1.9.9

1. Confirm ePaper SaaS v1.1.9.8 is currently deployed.
2. Take a Cloudways on-demand backup.
3. Extract this hotfix into the application's outer `public_html`, overwriting matching files.
4. Keep the webroot as `public_html/public_html`.
5. Do not run `install.php`; there is no database migration.
6. No RabbitMQ/Supervisor restart is required.
7. Purge Varnish/application cache and hard-refresh `/login`.
8. Verify desktop and mobile login: no large blank area above `Welcome back`, quote directly below the security note, and no large blank area under the quote.
