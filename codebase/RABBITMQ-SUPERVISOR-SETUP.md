# RabbitMQ + Supervisor Setup — ePaper SaaS v1.0.1.0

## Responsibilities

RabbitMQ is the durable delivery broker. Supervisor keeps workers alive. MariaDB is the source of truth for processing state. Redis remains for cache/session/rate-limit/domain/entitlement/lock workloads.


## v2.0.0-alpha.2.3 Web Supervisor Setup

For existing installations, prefer the authenticated server-specific helper instead of manually editing placeholders:

```text
/admin/system/infrastructure/supervisor
```

It checks the CLI runtime, RabbitMQ, Redis, worker scripts and `storage/logs`, then generates the exact commands and a credential-free downloadable Supervisor configuration using the deployed application path.

After adding/restarting the programs, wait 15 seconds, refresh Worker Doctor, and run the end-to-end worker pipeline test from Infrastructure.

The web helper intentionally does not execute `supervisorctl` or spawn persistent processes.

## 1. Enable server packages

In Cloudways Server -> Settings & Packages -> Packages, enable RabbitMQ and Supervisor when available for your server. Do not uninstall Redis.

## 2. Install PHP AMQP client

SSH to the ePaper application root:

```bash
cd /home/master/applications/<APP_ID>/public_html
composer install --no-dev --optimize-autoloader
```

Confirm:

```bash
php -r "require 'vendor/autoload.php'; echo class_exists('PhpAmqpLib\\Connection\\AMQPStreamConnection') ? 'AMQP OK'.PHP_EOL : 'AMQP MISSING'.PHP_EOL;"
```

## 3. Configure broker credentials

Use the actual RabbitMQ host/user/password/vhost supplied for your Cloudways server. Avoid placing the password directly in command history:

```bash
export EPAPER_RABBIT_PASS='YOUR_RABBITMQ_PASSWORD'
php bin/configure-rabbitmq.php \
  --driver=rabbitmq \
  --host=127.0.0.1 \
  --port=5672 \
  --user='YOUR_RABBITMQ_USER' \
  --vhost='/' \
  --password-env=EPAPER_RABBIT_PASS
unset EPAPER_RABBIT_PASS
```

The tool edits the existing private `config/config.php`; it does not reset database/Platform settings.

## 4. Declare/test topology

```bash
php bin/queue-diagnostic.php
```

Expected queues/exchanges:

```text
epaper.jobs
epaper.render
epaper.assets
epaper.ocr
epaper.finalize
epaper.retry.30s
epaper.retry.5m
epaper.retry.30m
epaper.dead
```

## 5. Supervisor worker set

Use `SUPERVISOR.conf.example` as the command reference. The intended process counts are:

```text
Outbox Publisher : 1
Render Worker    : 1
Asset Workers    : 2
OCR Worker       : 1
Finalize Worker  : 1
```

Worker commands from the application root:

```bash
php bin/outbox-publisher.php
php bin/rabbit-worker.php --lane=render
php bin/rabbit-worker.php --lane=assets
php bin/rabbit-worker.php --lane=ocr
php bin/rabbit-worker.php --lane=finalize
```

For long OCR/assets callbacks, workers use php-amqplib's PCNTL signal heartbeat sender when PCNTL is available. The scheduler additionally recovers database jobs whose processing heartbeat becomes stale.

## 6. Processing semantics

`Process Now` creates a high-priority `EDITION_RENDER` job + transactional outbox event. The outbox worker publishes after MariaDB commits and waits for Rabbit publisher confirmation. Render creates page rows/HIGHRES assets and queues independent page asset/OCR jobs. Finalize runs only when the active pipeline has no remaining queued/running/retry page jobs.

Transient failures use Rabbit retry queues: 30 seconds, then 5 minutes, then 30 minutes. Jobs exceeding their configured attempts become FAILED and are represented in `epaper.dead`. OCR failures can result in READY-with-OCR-warnings; asset failures make the edition NEEDS_ATTENTION.

## 7. Health

Check:

```text
https://epaper.digiti.in/health/queue
https://epaper.digiti.in/health/ready
```

Processing Center also shows ready-message and consumer counts for the work/retry/dead queues.

## 8. Existing stuck edition

After switching to RabbitMQ, do not repeatedly click Process Now. If an old detached `process.php`/Tesseract process from v1.0.0.x is still holding the edition lock, inspect exact PIDs via SSH and terminate only the obsolete process if required. The Rabbit render worker will retry while the edition lock is busy. Then click Process Now once and watch the pipeline in Processing Center.
