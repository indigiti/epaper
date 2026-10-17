# EP-DI v2.0.1.8.1 — Schema Safety & Subscriber Recovery

Server-side hotfix for deployments where v2.0.1.8 code was uploaded before its database migration.

## Immediate recovery

Open **Admin → Upgrade Center** and run the safe repair after taking a current database backup. The repair now detects and applies the packaged v2.0.1.5 mobile-auth, v2.0.1.7 flexible-reader, and v2.0.1.8 trusted-app/desktop-login migrations when missing.

Alternatively, for the reported `ep_reader_devices` error, execute `database/upgrade-v2.0.1.8-trusted-app-desktop-login.sql` once against the EP-DI database.

## Hotfix behavior

- `/admin/subscribers` degrades safely instead of throwing PDO 42S02 when reader-device/session tables are absent.
- Upgrade Center detects current reader-auth migrations rather than reporting the schema aligned incorrectly.
- Subscriber enable/disable remains usable while the trusted-login schema is missing.
- No new database migration is introduced by 2.0.1.8.1.
- Android application remains 2.0.1.8; this is a server/schema-safety hotfix.
