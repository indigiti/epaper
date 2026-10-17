# Untracking EP-DI runtime storage safely

**Prepared:** 2026-10-17. **Status: DRAFT ONLY — DO NOT MERGE, DEPLOY OR RUN `git pull` ON CLOUDWAYS.**

This change is about **source-control hygiene**, not deleting live data. Historical ePaper files can still be required by current MariaDB editions, OCR, customers' clip URLs and offline licenses. The user has approved removal of old repository storage data, but **no authoritative Cloudways/media/DB backup and isolated restore is currently verified**.

## Baseline inventory (Git-tracked files in source branch)

| Path | Count | Approximate size | Retention |
|---|---:|---:|---|
| `storage/media/` | 4,373 | 350.5 MB | Published pages, WebP/JPEG/tile assets; preserve |
| `storage/uploads/` | 11 | 67.6 MB | Original source PDFs; preserve |
| `storage/clips/` | 43 | 7.0 MB | Reader-created clipping exports; preserve |
| `storage/cache/` | 257 | 0.3 MB | May be regenerable; verify compiled editions |
| `storage/logs/` | 5 | <1 KB | Operational evidence; retain separately if required |
| `storage/tmp/` | 1 | 0 | Keep placeholder |
| **Total** | **4,690** | **425.3 MB (~405.6 MiB)** | **4,684 non-placeholder files removed from the proposed Git tip only** |

Seven empty placeholders (`.keep` and `.gitkeep`) preserve expected runtime directories. The new source branch does not remove files from historical Git objects, forks, previous clones, release ZIPs or a deployed Cloudways instance **unless a deployment/checkout is later applied**.

## Why a merge can destroy live data

Git treats previously tracked media files as deletions after this commit. If this branch reaches a Cloudways checkout, `git pull`, `git reset --hard`, `rsync --delete`, a release overlay or a CI-generated artifact, those files may be deleted from the **actual server**. A `.gitignore` entry does not prevent deletion of files that were tracked on the old commit.

**No Git merge, push-to-deploy, or automated sync is safe until a content-preservation migration is completed.**

## Mandatory operator gate — after other staging preparation, before any cleanup deployment

1. Export a full, consistent Cloudways application snapshot, MariaDB backup and **separate media/PDF/clip backup**, with object counts, checksums and retrieval instructions stored privately.
2. Restore the backup to isolated staging with fake/non-production external provider credentials. Validate published pages, WebP/tile assets, original source PDFs, clipping URLs, signed media, offline entitlements and the current edition database.
3. Ensure runtime `storage/` persists *independently* of deploy code: a protected persistent directory or verified persistent mount with preserved web/FPM/cron/Supervisor paths, ownership and permissions. Do not merely copy it into another location without updating every code reference and migration script.
4. Test the **exact Cloudways/DigiOps deployment mechanism** including its delete/exclude flags against a disposable staging clone; confirm zero loss of authoritative media, uploaded PDFs and clips.
5. With a verified rollback point, stage the reduced source and rerun PHP lint, release-check, Reader, OCR, Clipping, Offline Vault, queues, scheduler, media URL and tenant-isolation tests.
6. Follow the existing incident sequence: **verified backup/restore before credential rotation**, then coordinated secret history cleanup. Git history rewriting to truly reclaim remote storage is a separate disruptive operation and must not happen blindly.

## Release integrity

`SHA256SUMS` and `BUILD-MANIFEST-2.0.2.1.md` describe the **original historical v2.0.2.1 release layout**, including now-untracked media and vendor. Running `sha256sum -c SHA256SUMS` on the slim source tree is expected to fail. These old records are intentionally preserved and are **not certification of the new slim source**.

For a new deployable release, build a fresh artifact with Composer dependencies and a new, versioned integrity manifest covering the complete runtime artifact, excluding runtime/user data. Do not overwrite historical hashes or silently label the slim checkout as v2.0.2.1 verified.

## Repository size versus history

At HEAD the current checked-out source loses approximately 405.6 MiB of tracked runtime content. **Repository history size remains essentially unchanged** because old blobs are still reachable through previous commits and tags. A later coordinated, all-ref `git filter-repo` history rewrite could reclaim Git storage and remove sensitive historical content—but only after media backup, credential revocation, collaborator coordination and an authorized recovery plan.

## Do not remove now

- `public_html/uploads/branding/` — current publication logos and favicons remain tracked until a tested public branding migration.
- `vendor/` — Composer-installed dependencies remain bundled as before pending a separately proven Cloudways artifact/install workflow.
- `database/`, `app/`, `bin/`, `mobile-android/` and `release/` — unchanged.


## Operator-reported backup done; restore verification pending

As of 17 October 2026, the operator reported that a Cloudways backup has been created. This is a **user-reported milestone**, not a remotely inspected Cloudways backup. No verified MariaDB/media restore or deployment rehearsal has been supplied yet.

An optional **read-only** integrity comparator is now available:

1. Locate the precise, frozen content tree inside the completed backup, especially \`storage/media\`, \`storage/uploads\`, \`storage/clips\` (and optional \`cache\`, \`logs\`).
2. On a private system with access to the extracted backup tree, generate the backup inventory:

       php bin/restore-verification.php --storage=/private/backup/extracted/storage > /private/backup-storage-inventory.json

3. After restoring to a **separate isolated staging installation**, compare the restored media with the backup inventory:

       php bin/restore-verification.php --storage=/private/staging/storage --compare=/private/backup-storage-inventory.json

4. The comparison checks each relative file path and its SHA-256 content digest, count and total bytes without printing user filenames or hashes individually. Media, source PDFs and clips must match; caches/logs may differ. **This does not verify database consistency**, nor source/PDF references stored in MariaDB, Reader behavior, encrypted offline licenses, permissions, webroot, worker services or the actual deployment delete semantics.
5. Independently restore and verify MariaDB row/edition counts and links to media, sample pages, clippings, offline entitlements, and a synthetic staged publication. Validate the intended Cloudways deployment process against this replica, proving that untracked runtime content survives \`git pull\`, \`rsync --delete\`, release activation and rollback.
6. Report the sign-off in a private operator record with the backup ID, snapshot timestamp, restore time, database and media checks, owner and rollback evidence. Do not paste sensitive locations or raw backup files into a public PR.

The actual staging restore and cutover remain **NOT VERIFIED** until executed and evidenced.


## Separate Cloudways staging environment reported ready (operator report)

The operator has now reported that the backup is present in a **separate Cloudways staging environment**. This establishes a reported staging milestone, but is **not an independent confirmation** that both the MariaDB restore and all original PDFs/media/clips have been restored and tested.

For a staged installation containing restored data, the new **read-only** file-reference and schema-integrity audit is:

    EPDI_STAGING_RESTORE_ONLY=1 EPDI_STAGING_DB_NAME=<EXACT_STAGING_DB_NAME> \
    php bin/staging-restore-acceptance.php

**Do not check out, reset to or pull this slim-storage branch on any existing staging/production installation:** Git would delete formerly tracked media. Until a content-preserving deployment rehearsal has passed, transfer only the two new read-only checker files (`app/Core/RestoreReferenceAudit.php` and `bin/staging-restore-acceptance.php`) into the isolated staging application by the operator-approved safe file-copy procedure. Run the checker from the existing staged `codebase/` installation with private `EPDI_CONFIG_PATH` and `EPDI_RUNTIME_PATH` as applicable. Use a read-only MariaDB principal; the script also marks its session read-only. It reports row counts, missing file references and cross-tenant orphan reference counts, **not** actual document names, user identities or file paths. It must be executed against an isolated **staging** database, never against production.

Next run the separate backup-vs-restore SHA-256 comparator (above), \`php bin/health.php\`, \`php bin/queue-diagnostic.php\`, \`php bin/readiness-check.php --json\` with the correct staging tenant, and authenticated staging browser tests. Never run \`bin/tenant-isolation-diagnostic.php\` on a live/production database: that diagnostic inserts and deletes fixture data.

**Stop** if any original PDF/asset/current clip is missing, any parent is orphaned, the restored database is empty, tenant isolation fails or stage deployment wipes files. No deletion PR may be merged until the restore and deployment-preservation procedure has independent evidence and operator sign-off.


## Cloudways ZIP backup format — non-destructive preflight

The operator reports the Cloudways backup is in ZIP format. It has **not** been inspected or test-restored by this repository. Keep the original ZIP unchanged, in private storage; calculate its SHA-256 digest and preserve it in the private recovery record.

The following new preflight reads the ZIP *without extracting files* (and does not display file names or data):

```bash
cd codebase
php bin/backup-zip-preflight.php --zip=/private/backups/epdi-backup.zip
# Optional: stream every entry to test readability, without extraction
php bin/backup-zip-preflight.php --zip=/private/backups/epdi-backup.zip --verify-contents
```

The results include whether the ZIP structure is safe, and counts for `storage/media`, `storage/uploads/*.pdf`, `storage/clips`, publisher branding, application entry files and potential SQL exports. The verifier refuses parent-directory traversal, absolute paths, Windows drive paths and Unix symlink entries. It reports suspicious ZIP paths without disclosing filenames.

**Important:** A ZIP containing only application files is not necessarily a full MariaDB backup. SQL/database exports may be separate in Cloudways. An SQL-looking filename is only an indicator, not proof that the backup is consistent or restorable. If the ZIP bundles another archive, inspect that archive separately on an isolated system.

After inspection, extract only onto a **disposable, isolated staging destination** using a tool that rejects unsafe paths; never unzip over production. Then run `bin/restore-verification.php`, `bin/staging-restore-acceptance.php` and authenticated staging/Reader/OCR/Clipping tests, with a separate verified MariaDB restore.

No Git merge or deployment against the live checkout is authorized from ZIP preflight success alone. The source-cleanup PR still contains deletion entries for the formerly Git-tracked production data.
