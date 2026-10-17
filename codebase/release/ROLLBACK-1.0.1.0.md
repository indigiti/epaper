# Rollback — ePaper SaaS v1.0.1.0

Do not roll back by deleting queue/job/outbox rows. MariaDB is authoritative.

Safe fallback path:

1. Stop the RabbitMQ-specific Supervisor workers (`epaper-outbox`, `epaper-render`, `epaper-assets`, `epaper-ocr`, `epaper-finalize`).
2. Run `php bin/configure-rabbitmq.php --driver=database`.
3. Run the database worker (`php bin/worker.php`) under Supervisor.
4. Keep Redis/MariaDB/Platform/cron unchanged.
5. Diagnose pending/retry/failed jobs before attempting any data cleanup.

Application code can be restored from the pre-upgrade Cloudways backup if required. Existing publication/edition/page/media/OCR data is not migrated by v1.0.1.0.
