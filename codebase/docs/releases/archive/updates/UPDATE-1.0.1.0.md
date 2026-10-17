# Upgrade v1.0.0.6 -> ePaper SaaS v1.0.1.0

Release state: **TESTED**. No MariaDB schema migration is required. Existing ePaper content, Platform binding, users, settings, media, OCR and Reader data are preserved.

## Install order

1. Take an on-demand Cloudways application/database backup.
2. Enable RabbitMQ and Supervisor on the Cloudways server. Keep Redis enabled.
3. Extract the v1.0.1.0 hotfix into the existing ePaper application's outer `public_html` and overwrite matching files. Keep Webroot `public_html/public_html`.
4. SSH to the outer application root and run `composer install --no-dev --optimize-autoloader`.
5. Configure the existing private config with `bin/configure-rabbitmq.php` using the actual RabbitMQ credentials.
6. Run `php bin/queue-diagnostic.php`. It must connect and declare/check the topology.
7. Add/start the five Supervisor worker groups from `RABBITMQ-SUPERVISOR-SETUP.md`.
8. Keep the one-minute scheduler cron active.
9. Open `/health/queue` and `/health/ready`.
10. In Admin -> Processing Center confirm RabbitMQ Connected and expected consumer counts before starting/retrying an edition.

## Recommended commands

```bash
cd /home/master/applications/<APP_ID>/public_html
composer install --no-dev --optimize-autoloader

export EPAPER_RABBIT_PASS='YOUR_RABBITMQ_PASSWORD'
php bin/configure-rabbitmq.php --driver=rabbitmq --host=127.0.0.1 --port=5672 --user='YOUR_USER' --vhost='/' --password-env=EPAPER_RABBIT_PASS
unset EPAPER_RABBIT_PASS

php bin/queue-diagnostic.php
php bin/release-check.php
```

## Current Pune Mirror stuck job

The new Process Now no longer launches a detached PHP processor. Old v1.0.0.x RUNNING processes can still exist until stopped/finished. The new render worker uses the same edition lock and will retry safely. If an old process remains abnormally alive, inspect with:

```bash
ps aux | grep -E 'tesseract|bin/process.php' | grep -v grep
```

Terminate only the exact obsolete PID if needed; never use broad `killall php`/`pkill php`. Then click Process Now once.

## Rollback

If RabbitMQ cannot be made stable, stop the Rabbit Supervisor jobs and switch:

```bash
php bin/configure-rabbitmq.php --driver=database
```

Then run the legacy database worker under Supervisor. Do not delete Rabbit/outbox/job rows during rollback; MariaDB is authoritative and retained for diagnosis/recovery.
