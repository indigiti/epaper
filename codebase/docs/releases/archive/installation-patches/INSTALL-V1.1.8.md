# Install v1.1.8

1. Take a Cloudways on-demand backup.
2. Extract the v1.1.8 hotfix into the existing outer `public_html` over v1.1.7.
3. Keep Webroot `public_html/public_html`.
4. Do not run `install.php`; there is no database migration.
5. No RabbitMQ/Supervisor restart is required.
6. Purge Cloudways/Varnish cache and hard-refresh the Reader once.
7. Confirm Redis is enabled and connected in System Health.
8. Test `/subscribe` and `/account/subscription` on the current ePaper domain.
