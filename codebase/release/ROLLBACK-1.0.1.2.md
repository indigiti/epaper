# Rollback — v1.0.1.2

No schema migration is performed. To roll back, restore the pre-update application backup. If RabbitMQ is not ready, keep the queue driver on `database` and stop RabbitMQ Supervisor worker jobs. Do not delete processing/outbox rows; MariaDB remains authoritative.
