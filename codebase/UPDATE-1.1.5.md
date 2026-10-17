# ePaper SaaS v1.1.5 — Brand Assets + CRUD Foundation

## Install
1. Start from a working **v1.1.4.1 Source Page QR Hotfix** installation.
2. Take an on-demand Cloudways backup.
3. Extract this ZIP into the application's outer `public_html` and overwrite matching files.
4. Keep Webroot `public_html/public_html`.
5. Do not run `install.php`; there is no database migration.
6. No RabbitMQ/Supervisor reconfiguration is required.
7. Purge Cloudways/Varnish cache and hard-refresh once.

## Brand & Identity
`/admin/settings/brand` is now a dedicated multipart upload experience. Admins can upload/replace/remove Primary Logo, Dark Logo, Square Mark and Favicon without entering file URLs. Files are MIME/dimension/size validated and stored per tenant. With Imagick, the Square Mark automatically generates favicon, Apple Touch and PWA-size derivatives.

## CRUD Foundation
Admin record screens now expose appropriate Edit/Enable/Disable/Delete or Archive/Restore actions. Account Library pages expose title/note/visibility edits and Remove/Clear actions where safe. Audit, Error Center, Processing history and Platform billing remain deliberately non-destructive.

## Source Page QR hotfix
v1.1.4.1 QR encoder and clipping branding service are preserved exactly and protected by SHA-256 regression guards.
