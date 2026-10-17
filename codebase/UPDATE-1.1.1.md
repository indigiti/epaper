# Update to ePaper SaaS v1.1.1

1. Take an on-demand Cloudways backup.
2. Extract the v1.1.1 ZIP into the existing outer application `public_html` and overwrite matching files.
3. Keep Cloudways Webroot `public_html/public_html`.
4. No database migration is required.
5. No RabbitMQ/Supervisor configuration change is required. Worker restart is optional because processing code is unchanged.
6. Purge Cloudways/Varnish cache and hard-refresh once so the v1.1.1 service worker and assets replace v1.1.0.
7. Run `php bin/release-check.php`.
8. Verify Reader navigation, `/account` plus all six library subpages, and role-specific `/admin` navigation on desktop/mobile.
