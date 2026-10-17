# Rollback — v1.0.1.1

No database schema rollback is required.

To roll back, restore the v1.0.1.0 application files. If `config/runtime.php` was created by the Infrastructure Dashboard, either leave it in place (v1.0.1.0 ignores it) or move it aside. Do not expose or copy its RabbitMQ password into the public webroot.
