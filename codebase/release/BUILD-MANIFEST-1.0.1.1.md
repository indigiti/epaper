# Build Manifest — ePaper SaaS v1.0.1.1

Release type: additive infrastructure-management hotfix over v1.0.1.0.
Database schema changes: none.
Public/Reader route removals: none.

## Main changed files
- `app/App.php` — Infrastructure routes/UI, queue health worker summary.
- `app/Core/Auth.php` — canonical-host infrastructure authorization.
- `app/Core/Config.php` — private runtime configuration overlay.
- `app/Core/Uuid.php` — UUID validation helper.
- `app/Core/View.php` — Infrastructure navigation and asset version alignment.
- `app/Services/InfrastructureConfig.php` — secure RabbitMQ config, diagnostics, worker status and probes.
- `app/Services/OutboxPublisher.php` — queue probe outbox handling.
- `bin/rabbit-worker.php` — probe acknowledgement.
- `config/config.example.php` — infrastructure roles and expected worker counts.
- `public_html/assets/app.css`, `app.js`, `service-worker.js` — dashboard UI/probe polling/cache version.
- release manifests/tests/docs updated for v1.0.1.1.

## Guardrails
Reader/media/clipping/OCR/business routes are preserved. No tenant-owned business schema is altered. RabbitMQ password is never rendered back into HTML or audit metadata.
