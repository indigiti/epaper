# EP-DI slim release: Cloudways staging promotion gate

**Status:** REVIEW / OPERATOR-EXECUTED ONLY — not evidence of a live Cloudways rehearsal or production authorization.

## Why the Cloudways one-touch restore is not a deployment procedure

The operator reports that an application ZIP, MariaDB and media were backed up, restored into isolated staging and verified. This is useful recovery evidence. However, the proposed cleanup PR deletes 4,684 previously Git-tracked assets. A normal in-place `git pull`, `git checkout`, or `rsync --delete` against the live application can remove those assets before anyone notices. A successful restore does not prevent recurrence during subsequent deploys.

The Cloudways/DigiOps deployment driver is **not accessible to GitHub Actions** and has not been inspected by this change. Do not push this branch into the current live Cloudways checkout or trigger any deployment automation from merging the PR.

## What changed in source

- `php bin/slim-release-zip.php --output=/private/build/epdi-slim.zip` builds a ZIP from tracked runtime code with a package-level SHA-256 manifest. Package members are relative to the **application root**, not `codebase/`.
- `php bin/slim-release-zip.php --verify=/private/build/epdi-slim.zip` validates every member and rejects extra paths, duplicate entries, symlinks, tampering or runtime media. No file extraction is performed.
- Generated `storage/**` and **all** `public_html/uploads/**` (including newspaper branding) are intentionally excluded. Both must be preserved outside the checkout and mounted or linked before acceptance.
- The artifact includes the existing checked-in `vendor/` and Composer lockfile; actual PHP/FPM/worker dependency availability still needs staging validation.
- The embedded `__epdi/release-manifest.json` is a **new artifact manifest**. It does not replace the historical `SHA256SUMS` and is not a production deployment attestation.
- `php bin/slim-release-target-preflight.php` checks the isolated staged release: `storage` symlink, restored backup media inventory and `public_html/uploads` symlink to a persistent external directory.
- CI tests that the archive contains required runtime files, no storage/branding media or private config, and rejects tampering; simulated activation/rollback tests use synthetic files.

## Safe operator execution order — isolated Cloudways staging only

1. **Confirm the operator-reported recovery checkpoint privately:** identify the original verified backup, isolated restore result, database counts, PDF/page/clip inventory, and rollback reference. Never place backup ZIPs or secrets in GitHub.
2. **Inspect the real deployment implementation:** document whether DigiOps/Cloudways performs Git checkout, copy overlay, `rsync --delete`, ZIP extraction, immutable releases or symlink switching. Record whether it follows symlinks, how delete/exclusion rules work, and whether an automated GitHub merge would trigger a deploy. If unclear, STOP.
3. **Separate authoritative data from executable code:** make storage and public uploads persist outside *any* Git checkout or replaceable release directory. Preserve originals, clipping exports, tiles, logos, favicons, live private configuration and permissions. Validate PHP-FPM/web server, cron, Supervisor and worker execution contexts use the intended mounted paths.
4. **Build and verify the slim ZIP offline** from the reviewed commit (example):
   ```bash
   cd codebase
   php bin/slim-release-zip.php --output=/private/build/epdi-slim.zip
   php bin/slim-release-zip.php --verify=/private/build/epdi-slim.zip
   ```
   Do not extract over the existing Cloudways code checkout. First use a disposable, fresh, non-live release directory.
5. **Connect persistent paths within that isolated staging release** before running any application bootstrap. The expected layout is:
   ```text
   /staging/releases/next/app/...
   /staging/releases/next/public_html/index.php
   /staging/releases/next/storage -> /staging/shared/storage
   /staging/releases/next/public_html/uploads -> /staging/shared/public-uploads
   /staging/shared/storage/{media,uploads,clips,cache,logs}
   ```
   Locations are illustrative; never assume Cloudways uses these paths. Migrate with operator-approved file transfer that does not overwrite originals. Confirm ownership, permissions and webroot nesting.
6. **Verify the staged target read-only**, using a private inventory produced from the verified backup:
   ```bash
   php bin/slim-release-target-preflight.php \
     --app-root=/staging/releases/next \
     --persistent-storage=/staging/shared/storage \
     --persistent-public-uploads=/staging/shared/public-uploads \
     --backup-inventory=/private/backup-storage-inventory.json
   ```
   A passing result only confirms data paths and hashes; it explicitly does **not** certify the actual deployer, database, credential rotation, or production.
7. **Rehearse the *same* deployment driver as production** on a completely disposable staging clone. Snapshot inventory *before and after* activation, rollback, cache updates, worker restart, deliberate retained/media file changes and deployments with deletion options. Verify stable URLs, branded uploads, 2-reader session isolation, Owner/Admin/Editor/Operator roles, PDF→render→OCR→publish, clipping, signed media, offline entitlements, queues and cron/worker compatibility. Keep all real notification/payment integrations disabled.
8. **Rotate exposed credentials in live service consoles** (MariaDB, Redis, Digiti Platform, RabbitMQ and other compromised secrets), validating replacement identities before revocation. Treat app signing/encryption key separately: signed media, Offline Vault ciphertext, FCM and trusted devices require a migration or deliberate relogin/relicense procedure. This action is unavailable through the GitHub connection.
9. **After proof of credential revocation**, coordinate all-ref Git-history cleanup across tags, branches, clones and forks using an approved mirror workflow. Never force-push blindly: rewrite cannot retroactively revoke leaked secrets.
10. **Only after explicit live acceptance**, approve chained PRs in dependency order (#1 → #15 → #17), validate CI on final heads, and use the accepted release process. If promotion fails, stop publishing and follow the privately rehearsed rollback/Cloudways restore plan.

## Acceptance matrix

| Required item | Current status |
|---|---|
| Backup and isolated restore | OPERATOR REPORTED PASS; independently inaccessible here |
| Slim artifact build and source CI | GitHub branch tests (must be green on exact head) |
| External storage and branding path fixture | Local synthetic test, not Cloudways |
| Exact Cloudways/DigiOps deployment-driver rehearsal | NOT VERIFIED |
| Real authenticated staging, roles, reader and clipping | NOT VERIFIED by this run |
| Credential replacement/revocation evidence | NOT VERIFIED |
| Historical Git-object secret cleanup | NOT EXECUTED |
| Production deployment | BLOCKED |

## Never do this

Do not apply `git pull` on the current live media-tracking checkout, copy this ZIP over production, delete `storage` or `public_html/uploads`, force-delete remote Git history, assume green CI proves Cloudways integrity, or expose credentials/backup paths in CI logs. Keep PR #17 draft and avoid automatic deployment triggers.
