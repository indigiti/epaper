# Build Manifest — ePaper SaaS v1.0.1.2

Release type: cumulative infrastructure recovery hotfix.

## Fix
- Makes the v1.0.1.x RabbitMQ/Supervisor engine self-contained when upgrading directly from v1.0.0.6.
- Prevents `/admin/system/infrastructure` from collapsing into the global error page when queue-engine files are incomplete.
- Adds explicit service-file and php-amqplib dependency diagnostics.
- Keeps RabbitMQ configuration write-only for passwords and outside the public webroot.

## Cumulative engine files
Includes RabbitMQ transport, QueueService, ProcessingPipeline, OutboxPublisher, retry/permanent exceptions, bootstrap Composer loading, queue/worker CLIs, Supervisor example and dashboard runtime configuration.

## No schema migration
Existing MariaDB schema is unchanged.
