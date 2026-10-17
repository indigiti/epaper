# EP-DI — production security incident and cutover runbook
**Prepared:** 2026-10-17  
**Release candidate:** PR #1, branch audit/security-foundation-20261017  
**Status:** OPERATOR ACTION REQUIRED — no production credential rotation, backup restore, or history rewrite is certified by GitHub CI.

This runbook intentionally contains no credentials, usernames, customer data, app IDs or backups. Complete the private incident record in an approved secure operator system; never post secrets, logs with secrets, access tokens, or backup archives in an issue/PR.

## 0 — Containment (as soon as possible)
- [ ] Assign an on-call owner and record start time in a private incident system.
- [ ] Assume the prior committed MariaDB, Redis, Digiti Platform and application signing/encryption credentials have been exposed. Inspect for abuse and revoke active tokens after cutover.
- [ ] Limit database, Redis and RabbitMQ network access to trusted local/service hosts while rotating; check Cloudways/app/Platform logs.
- [ ] Take note of signed reader media, offline vault encryption/device pairing, mobile/desktop FCM, active sessions and cache keys using the app secret. Rotating this key without a compatibility plan may invalidate access to existing ciphertext/tokens.

## 1 — Verified backup and recovery (MUST precede destructive actions)
- [ ] Create a Cloudways on-demand application backup and separate consistent MariaDB backup. Record backup IDs, timestamps and checksums in the **private** incident record.
- [ ] Create a content-complete backup of original PDFs, media/editions, clips, uploads and server-only configuration. Existing tracked media is not a reliable production backup.
- [ ] Verify the backup actually contains authoritative database and media data and has correct restoration permissions.
- [ ] Restore the backups onto an isolated staging environment. Use different environment credentials and block outgoing customer notifications/payment calls.
- [ ] Verify restored database counts and sample published editions, original PDFs, reader thumbnails/tiles, clips and Offline Vault entitlement handling. Mark signed/encrypted media migration work separately.
- [ ] Obtain explicit sign-off from the recovery owner before cutover.

## 2 — Private config migration (MUST precede merging PR #1)
Current app root on Cloudways is the outer PHP application directory; the webroot is its nested public_html.

- [ ] Create a private path **outside** the application checkout and nested webroot (for example a sibling private directory with mode 0700). Do not commit its location or contents.
- [ ] Make secure copies of current config/config.php and config/runtime.php with mode 0600; preserve owner/group expected by PHP-FPM and Supervisor.
- [ ] Set EPDI_CONFIG_PATH and EPDI_RUNTIME_PATH to absolute paths for **all** execution contexts (PHP-FPM, cron, Supervisor worker and interactive CLI). Cloudways PHP-FPM may not inherit shell variables, so verify in each context without printing credentials.
- [ ] Alternative: provision ignored config symlinks that resolve outside the checkout; if used, verify your deployment system preserves them.
- [ ] With app code from this PR staged, run php bin/security-cutover-preflight.php --json in the same environment as each process. It checks presence/permissions/strength only; it **cannot certify rotation or network connectivity**.
- [ ] Run php bin/health.php, php bin/platform-diagnostic.php, php bin/queue-diagnostic.php and php bin/supervisor-preflight.php in staging.
- [ ] Stop if source sync, Git checkout, rsync --delete, PHP-FPM or Supervisor can remove/replace the only private copy.

## 3 — Rotate each compromised credential and validate
Carry out changes in a maintenance window, one service at a time, with a rollback plan.

- [ ] MariaDB: create or rotate dedicated least-privilege service principal; update private config; restart/reload appropriately and validate SELECT/INSERT against a staging test tenant. Revoke the old principal/password **only after** new access works.
- [ ] Redis: create/rotate least-privilege Redis ACL credentials; update both FPM and workers, verify cache/session/queue keys in staging; revoke old credentials after cutover.
- [ ] Digiti Platform: issue a replacement scoped service token; test domain/tenant resolution and Platform health; then revoke the old token.
- [ ] RabbitMQ: if the old or guest account is usable in production, create a dedicated vhost-scoped worker identity; validate every Supervisor lane/outbox, then revoke/disable old account.
- [ ] App signing/encryption key: inventory every consumer before rotation (media signatures, Offline Vault AES-GCM, FCM/device tokens, sessions). Where encryption lacks key-version migration, rotate with an intentional re-enrollment/relicense plan; do not silently corrupt old ciphertext.
- [ ] Other provider credentials (Twilio/Firebase etc.): audit sources and rotate any value that was ever committed or reused.
- [ ] In all cases: keep replacement credentials in private secret stores and **do not enter values into GitHub Issues/PRs, chat transcripts or console logs**.

## 4 — Security and feature acceptance on staging
- [ ] Verify GET and POST to /install.php are denied after configuration even if storage/installed.lock is removed (never do this experiment on production).
- [ ] Run authenticated Owner, Admin, Publisher Admin, Editor and Operator route test matrix; confirm direct POST cannot bypass new RBAC.
- [ ] Upload a harmless fixture PDF; verify PDF → render → OCR → publish, recovery retry, scheduler/outbox and failure handling with no tenant bleed.
- [ ] Check subscriber settings and denied management actions for Editor/Operator.
- [ ] Check reader cover/page/tiles, member and guest clipping, QR and sharing/export permissions, signed-media expiry and Offline Vault opening an already licensed edition.
- [ ] Check /health/live, /health/ready, /health/queue and admin production readiness. Inspect PHP/Supervisor logs for fatal errors and new security incidents.
- [ ] Re-check mobile viewport, desktop viewport and key keyboard/assistive technology paths. Browser visual QA remains separate from static CI.

## 5 — Controlled history remediation AFTER rotation and verified backups
- [ ] Coordinate a maintenance pause for all collaborators and every automated deployment/clone; inventory branches, tags, forks and PRs. PR diffs and release artifacts may contain historic secrets.
- [ ] Using a disposable **mirror clone**, prepare a git-filter-repo rewrite to remove historical config/config.php and config/runtime.php plus any verified credential-containing paths from **all refs**. Review affected refs and backups before force push.
- [ ] Have an authorized repository operator verify the cleaned Git history independently; perform a controlled force-with-lease migration only after everyone can re-clone and CI/release tooling is reconciled.
- [ ] Ask owners of forks/clones/caches to clean their copies; GitHub's object retention and external mirrors cannot be guaranteed by a branch rewrite. Contact GitHub Support if cached sensitive objects remain.
- [ ] Do not equate Git-history cleanup with credential invalidation: both must be documented.

## 6 — Production release, rollback and acceptance
- [ ] Schedule the maintenance window, freeze publishing, record baseline commit, prepare rollback release and backups.
- [ ] With secret store and restore verified, deploy the reviewed security PR to staging; green GitHub CI alone is not deployment acceptance.
- [ ] Re-run cutover preflight, site/readiness, worker lanes, publishing, read/clip/offline and admin permission QA.
- [ ] Deploy via the approved Cloudways release process with versioned artifact and monitored health checks. Never deploy the checkout before external configuration is ready.
- [ ] Monitor incidents, scheduler, RabbitMQ, media errors, token validation and login success; roll back code **only with a compatible credential/database strategy**.
- [ ] Sign off production acceptance in a private change record. Only then unfreeze publishing and start frontend/clipping enhancements.

### Operational evidence to retain privately
Backup ID + restore rehearsal outcome; old credential revocation timestamps (never actual values); new identity least-privilege verification; signing key migration decision; fully cleaned ref report; Cloudways deployment reference; role matrix outcomes; PDF processing/reader/clip smoke outcomes; responder and approver.

## Repo-level verification (not proof of live operations)
From repository root: GitHub Actions EP-DI PHP Security and Regression must be green.  
From installed application root, with private config and correct environment: php bin/security-cutover-preflight.php --json && php bin/health.php && php bin/queue-diagnostic.php && php bin/readiness-check.php --tenant=<REAL_STAGING_TENANT_GUID>.
