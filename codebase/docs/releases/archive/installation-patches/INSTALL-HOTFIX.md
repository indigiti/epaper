# Install v1.1.6.1

Requires ePaper SaaS v1.1.6.

1. Take a Cloudways on-demand backup.
2. Extract this ZIP into the existing outer `public_html` and overwrite matching files.
3. Keep Webroot `public_html/public_html`.
4. Do not run `install.php`; no database migration exists.
5. No RabbitMQ/Supervisor restart is required.
6. Purge Cloudways/Varnish cache and hard-refresh once so service-worker cache v1.1.6.1 activates.

This hotfix intentionally excludes `app/Services/QrCode.php` and `app/Services/ClipService.php`.
