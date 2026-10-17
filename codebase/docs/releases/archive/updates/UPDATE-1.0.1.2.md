# ePaper SaaS v1.0.1.2 — Cumulative Infrastructure Recovery

This hotfix is deliberately cumulative. It can be installed directly over v1.0.0.6, v1.0.1.0, or v1.0.1.1.

## Why this exists
The v1.0.1.1 Infrastructure Dashboard was a delta that assumed the RabbitMQ/Supervisor engine from v1.0.1.0 was already installed. If the dashboard delta was applied directly over v1.0.0.6, `/admin/system/infrastructure` could fail with a missing service class before the page rendered.

## Install
1. Take a Cloudways application backup.
2. Extract this hotfix into the application's outer `public_html` and overwrite matching files.
3. Keep Webroot `public_html/public_html`.
4. Do not run `install.php` and do not run database migrations.
5. Open `/admin/system/infrastructure`.
6. If the AMQP client shows MISSING, run `composer install --no-dev --optimize-autoloader` once in the outer application root.
7. Configure/test RabbitMQ in the dashboard.
8. Configure the displayed worker commands in Cloudways Supervisor and restart the worker jobs.
9. Run the end-to-end worker pipeline test.

No existing publisher data, Reader data, Platform binding, OCR data, or ePaper media is changed.
