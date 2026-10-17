# Update to ePaper SaaS v1.1.0

1. Take a Cloudways on-demand backup.
2. Extract the cumulative v1.1.0 ZIP into the application's **outer** `public_html` and overwrite matching files.
3. Keep Webroot set to `public_html/public_html`.
4. Do not replace `config/config.php` or `config/runtime.php`; they are not packaged.
5. If Composer dependencies are already installed for RabbitMQ, no Composer action is required. On a fresh deployment run `composer install --no-dev --optimize-autoloader`.
6. Restart all ePaper Supervisor jobs: Outbox, Render, Assets, OCR and Finalize.
7. Purge Cloudways/Varnish cache and hard refresh browsers.
8. Run `php bin/release-check.php`.
9. Confirm `/health/ready`, `/health/platform`, `/health/queue` and `/admin/system/release-audit`.
10. Device QA: mobile pinch in/out/pan, desktop zoom/pan/reachable edges, Fit Page/Width, page navigation, Clip, Smart Article, Offline and Account Library.

No schema migration is required.

## Compatibility

- Digiti Platform v1.0.2+ is required for the first-party direct-auth flow.
- Existing RabbitMQ/Supervisor runtime configuration is preserved.
- Reader delivery assets are WebP; reprocessing an edition will regenerate HIGHRES/PREVIEW/THUMB/Deep Zoom assets under the v1.1.0 media policy.
