# ePaper SaaS v1.0.1.1 — Infrastructure Dashboard

## Purpose
Adds canonical-host application infrastructure administration so RabbitMQ and Supervisor worker health can be configured and tested without SSH for normal day-to-day diagnostics.

## Install over v1.0.1.0
1. Take a Cloudways backup.
2. Extract the hotfix into the application's outer `public_html` and overwrite matching files.
3. Keep Webroot `public_html/public_html`.
4. Do not run `install.php`; there is no schema migration.
5. Hard refresh the admin dashboard.
6. Open `/admin/system/infrastructure` from the canonical `epaper.digiti.in` host using an OWNER or ADMIN membership.

## Dashboard capabilities
- Configure queue driver and RabbitMQ host/port/vhost/username/password.
- RabbitMQ password is write-only in the UI and saved to `config/runtime.php` outside the public webroot with mode 0600 where supported.
- Test RabbitMQ credentials before saving.
- Configure expected Supervisor worker counts.
- See live worker heartbeats and RabbitMQ consumer counts.
- Run an end-to-end probe through MariaDB outbox → RabbitMQ → render/assets/OCR/finalize Supervisor workers.
- View safe `/health/queue` diagnostics.
- Generate/copy the exact worker commands for Cloudways Supervisor.

## Important boundary
The dashboard does not attempt to start, stop, or reconfigure the Cloudways Supervisor service itself. Cloudways remains authoritative for process lifecycle. ePaper SaaS verifies Supervisor by worker heartbeats and a real RabbitMQ probe, which avoids requiring privileged `supervisorctl` access from PHP-FPM.

## Security
Infrastructure controls are available only on the configured canonical application hostname and only to roles in `auth.infrastructure_roles` (OWNER and ADMIN by default). Customer custom-domain admins cannot change shared queue infrastructure.
