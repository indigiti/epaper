# Persistent storage deployment gate — EP-DI / Cloudways

Operator report on 17 October 2026: Cloudways ZIP verified, isolated restore completed, database/media integrity matched and Reader, PDF and clipping checks passed. This was reported by the operator, not independently observed through Cloudways.

## Why we still need a release rehearsal

PR #17 stops tracking thousands of previously tracked files under storage/media, storage/uploads and storage/clips. A destructive Git checkout, git pull or rsync --delete applied to the live historical working tree can remove these files, even when the original backup was successfully restored to staging. Recoverability and non-destructive deployment are different gates.

## Safe staged release procedure

1. On a disposable staging release, retain a separate, verified copy of media, PDF originals and clips in an operator-managed persistent storage directory OUTSIDE the new code checkout and web document root.
2. Arrange APP_ROOT/storage as an actual symlink pointing to the separate persistent directory. Preserve ownership and permissions for PHP-FPM, CLI cron and Supervisor. Do not switch the live installation here.
3. Generate a private backup inventory from the frozen backup source with bin/restore-verification.php (see STORAGE-GIT-CLEANUP-2026-10-17.md). Do not commit it.
4. Run the read-only preflight from the slim source checkout:

    php bin/storage-deployment-preflight.php --app-root=/staging/epdi-release --persistent-storage=/staging/persistent/storage --backup-inventory=/private/backup-storage-inventory.json

5. Require ok=true. The preflight checks that PHP entry points exist, the storage link resolves outside the checkout, and content hashes/counts for originals, media and clips match the backup. This gate does NOT verify the actual deployment driver or production credential rotation.
6. Rehearse the exact DigiOps/Cloudways deployment mechanism, including delete/prune flags and rollback, using the disposable staging clone. Repeat the storage preflight, Reader, OCR, clipping, offline license, processing worker and queue tests after deployment.
7. Create a NEW versioned release manifest; the old SHA256SUMS file and v2.0.2.1 build manifest are pinned history and do not certify the slim checkout.
8. Only after the storage-preservation mechanism and restore evidence are signed off, rotate/revoke leaked live credentials via the Cloudways/MariaDB/Redis/RabbitMQ/Digiti consoles with approval and recheck the application. Do not paste secrets into GitHub.
9. Do not blindly rotate app.key because Offline Vault CEKs, encrypted FCM tokens, media signatures and device fingerprints depend on it. Use an approved migration/re-enrollment plan.
10. After secret revocation and a coordinated all-ref history purge, prepare a controlled production release. Confirm whether merging to main triggers DigiOps automatic deployment before merging PR #1, #15 or #17.

## Read-only preflight limitations

The output flags deployment_driver_verified=false, credentials_rotated_verified=false and production_deployment_approved=false even if the local filesystem gate passes. A proper staging deployment rehearsal, auth tests and operator acceptance remain mandatory. No production services are changed by this command.