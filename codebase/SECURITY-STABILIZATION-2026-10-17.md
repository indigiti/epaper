# EP-DI security stabilization — 2026-10-17

This branch removes runtime secrets and installation state from the **current source tree**, adds a role-aware admin dispatcher, introduces a one-time installer authorization gate, removes a publicly callable legacy hotfix, and restores repository-root regression CI.

## Production action required before merge or deployment

1. Treat every credential previously committed under `config/config.php` and `config/runtime.php` as exposed. Rotate/revoke **all** app-signing/encryption keys, MariaDB and Redis passwords, Digiti Platform tokens, and any RabbitMQ credentials. Do not re-use old values.
2. Retain a secure, operator-controlled copy of live configuration **on the server outside Git** before any checkout/sync. This branch intentionally does not change production services.
3. Evaluate encrypted Offline Vault, media HMAC tokens, cookies and FCM encrypted push tokens before rotating the application encryption key. Ensure a controlled migration or re-enrollment plan.
4. Remove exposed values from GitHub commit history using a coordinated repository-history rewrite after rotation. A new commit cannot remove old history, tags, forks or previously downloaded copies.
5. Review application/Platform/MariaDB/Redis logs for unauthorized access and invalid tokens. Restrict credentials to least-privilege identities.
6. Fresh installs: generate a random 32+ character installer secret, set `EPDI_INSTALL_TOKEN` in the private PHP-FPM environment or save a server-only `config/install.token` file (permissions 0600), and enter it in `/install.php`. The token must not be committed.
7. Verify Cloudways configuration preservation before deploying this change. Roll back from a verified backup if necessary.

## Release gates

- Run root GitHub CI and `cd codebase && php bin/release-check.php`.
- Confirm denied requests for editor/operator on subscriber, global settings and infrastructure endpoints.
- Confirm publisher/admin workflows, upload/process/publish and offline reader on a staging replica.
- Existing generated media and upload assets remain tracked in this branch: **do not delete or move them without first migrating/backing up live content and verifying storage paths**. The new ignore rules prevent future accidental additions; current tracked media requires a separate planned migration.
- The root workflow tests PHP and web assets; Android release signing, browser visual QA and Cloudways live acceptance are separate mandatory gates.

## Phase 2 hardening

- `InstallerGuard` blocks setup whenever either `config/config.php`, an operator-provided `EPDI_CONFIG_PATH`, or `storage/installed.lock` exists. Removal of the lock alone no longer reopens setup. Installer GET and POST are no-store and noindex.
- Install attempts require a 32+ character secret via `EPDI_INSTALL_TOKEN` or `config/install.token`. New config and lock writes request 0600 file permissions.
- `EPDI_CONFIG_PATH` may point to a private PHP configuration file **outside the Git checkout**. Set it consistently for web PHP-FPM, queue workers, cron and CLI commands. `EPDI_RUNTIME_PATH` likewise relocates queue/media settings; Infrastructure Dashboard writes to that same path. The target directories must already exist and be writable if settings editing is required.
- Provision and test the external configuration **before deploying** removal of the old tracked config. Do not allow Git checkout or deployment synchronization to delete the only production copy.
- Role authorization is now method-aware: editor/operator processing pages are read-only, processing actions have exact path patterns, and Publisher Admin cannot use global recovery or queue repair. Direct-route tests cover bypass-shaped URLs.
- This code-level hardening cannot replace authenticated staging tests or production secret rotation/history cleanup.
