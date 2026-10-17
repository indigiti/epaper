# Rollback — ePaper SaaS v1.1.5.1

No schema migration is included.

To roll back, restore the v1.1.5 versions of:
- `app/App.php`
- `VERSION`
- `manifest.json`
- `bin/release-check.php`

No QR/Clip/Reader/RabbitMQ/Supervisor/database rollback is needed because those files are not changed by this hotfix.
