# EP-DI v2.0.1.4 — Direct Processing & Queue Recovery

## Install over v2.0.1.3

1. Back up application files, MariaDB and storage. Pause admin publishing actions and the scheduler.
2. Stop all existing EP-DI processing workers and the outbox publisher. Verify their PHP/PDF/OCR subprocesses have exited. Old workers must not coexist with this release during the upgrade.
3. Extract the ZIP into the existing application root containing app/, bin/, config/, public_html/ and VERSION. Preserve config/config.php, config/runtime.php, secrets and customer storage. This package contains no live config, customer media or test data.
4. No database migration is required on an aligned v2.0.1.3 installation. Do not reinstall/reset the application.
5. Reload PHP/OPcache; restart existing Supervisor worker groups and the scheduler. Hard-refresh the admin and confirm version 2.0.1.4.
6. On Editions, use the play icon **Process immediately** for an unfinished edition. Open processing details to confirm the request changes from QUEUED to RUNNING, and that stage progress advances.

## How immediate processing works

The POST action saves a durable tenant-scoped recovery request and attempts to launch `bin/direct-recovery-worker.php` as a detached PHP CLI process. PDF work does not run inside the browser's PHP-FPM request. The runner claims existing database jobs directly, using the exact same locks and processor as the RabbitMQ workers. RabbitMQ and its outbox publisher are not required to execute that requested work. Completed jobs are skipped; late broker copies are recognized as terminal duplicates.

The play icon and the backward-compatible Recover action both request this direct path. Repeated requests do not create new processing pipelines or duplicate broker dispatches. Reprocess remains a separate deliberate complete rebuild. Cancellation and protected edition states remain enforced.

A launch attempt is not proof of execution. The detail page shows actual request state/activity, jobs handled, waiting reason and errors. Another live worker's job lock is respected; the recovery runner does not force-kill or steal its work. Normal retry delays still apply after a failed attempt.

## Hosting requirements and fallback setup

The web launcher needs `exec`, `nohup`, a working PHP CLI found on PATH (8.1+), writable storage/logs and permission to start a background child process. PHP CLI must have the application's normal modules, including PDO MySQL, Imagick, DOM/XML and mbstring. PDF/OCR need the existing pdftoppm/Tesseract tools and relevant language packs. Shared hosts may prohibit or terminate detached processes.

If the request stays QUEUED with no runner activity, run from the application's root under its normal application user:

```bash
php bin/direct-recovery-worker.php --once --max-seconds=1800
```

This processes pending direct requests without RabbitMQ. A run is limited to 30 minutes between jobs; an in-flight job may take longer. The PDF/OCR subprocess timeouts still apply. If work remains, run again or configure the persistent worker below.

For dependable recovery independent of browser-launch permissions, supervise:

```bash
php /ABSOLUTE/APP/ROOT/bin/direct-recovery-worker.php
```

Use DIRECT-RECOVERY-SUPERVISOR.conf.example with your actual application root, PHP CLI path and application user. This optional additional worker has one installation-wide lock, so overlapping launches do not multiply recovery workers. The existing render/assets/OCR/finalize/outbox topology is retained.

You can disable automatic web launching in config/runtime.php using `processing.direct_launch => false` while keeping the supervised recovery worker. Preserve existing configuration keys when adding it.

## Publishing and progress

Direct recovery leaves manually published editions at READY for review. If an edition already has AUTO_READY intent or its selected schedule is due, the runner uses the same guarded publishing function to publish it. Future schedules are not published early. This does not create a recurring scheduler heartbeat: cron must still be repaired for ordinary scheduling and maintenance.

Editions now display overall percentage and Render/Assets/OCR counts. Queued and retry-wait jobs with no updates for more than 20 minutes are included in stalled warnings. Connected consumers are still connection counts, not proof of completed work. Each job retains its last 16 trace events plus current stage/error.

## Scope limits

This is a web-triggered CLI fallback, not an incremental processor running inside PHP-FPM. It cannot bypass missing image tools, disk/memory limits, a live worker's lock, or host restrictions. Partial render attempts that dispatched children without a completed render checkpoint still require Cancel followed by Reprocess; completed render handoffs can resume without replacing pages.

Do not purge RabbitMQ queues to apply this release. Shared/old messages are not assumed to belong to the current edition.

## Acceptance on your server

Use an unpublished test edition first. Confirm play/Recover, stage progress, Cancel, Trash/Restore, existing PDF reader/media, login/entitlements, manual publication and a future schedule. Confirm the real RabbitMQ workers complete and acknowledge jobs after restart. The release report distinguishes local tests from live deployment checks.
