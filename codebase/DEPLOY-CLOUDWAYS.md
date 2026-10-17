# Deploy ePaper SaaS v1.0.1.1 on Cloudways

## Fresh install

Create/choose the Custom PHP application for `epaper.digiti.in`, use a dedicated ePaper MariaDB database, extract this package into the application's outer `public_html`, and keep the Webroot:

```text
public_html/public_html
```

Map the domain, install SSL, and keep application/config/storage/bin outside the nested public webroot. Complete the Digiti Platform product/application/service-account/domain setup from `PLATFORM-SETUP.md`.

## Before the browser installer

Enable RabbitMQ and Supervisor on the Cloudways server. RabbitMQ availability depends on the server plan/package options. Then SSH to the outer application root and install the AMQP client dependency:

```bash
cd /home/master/applications/<APP_ID>/public_html
composer install --no-dev --optimize-autoloader
```

Do not put RabbitMQ passwords in shell history if avoidable. The browser installer can configure the queue driver for a fresh installation; existing installations should use `bin/configure-rabbitmq.php` as documented in `UPDATE-1.0.1.1.md`.

## Browser installer

Open:

```text
https://epaper.digiti.in/install.php
```

Provide MariaDB, Redis, Digiti Platform and RabbitMQ settings. The installer writes private `config/config.php`, creates/validates the schema, and locks itself after successful setup.

## Workers

Configure the five long-running Supervisor worker groups described in `RABBITMQ-SUPERVISOR-SETUP.md`:

```text
outbox   1
render   1
assets   2
ocr      1
finalize 1
```

Start with conservative concurrency; Tesseract/Imagick/pdftoppm are CPU/RAM intensive. Increase only after observing server resource use.

## Scheduler

Keep one application cron entry:

```text
* * * * * php /home/master/applications/<APP_ID>/public_html/bin/scheduler.php >> /home/master/applications/<APP_ID>/public_html/storage/logs/scheduler.log 2>&1
```

The scheduler handles scheduled publishing, Platform heartbeat/state, legacy cleanup, and recovery of abandoned RabbitMQ jobs whose database heartbeat stopped.

## Verification

From SSH:

```bash
php bin/health.php
php bin/platform-diagnostic.php
php bin/queue-diagnostic.php
php bin/tenant-isolation-diagnostic.php
php bin/release-check.php
```

Then check `/health/ready`, `/health/queue`, and Admin -> Processing Center. Upload a test PDF and verify Render -> Assets/OCR -> Finalize -> READY before marking the deployment VERIFIED.
