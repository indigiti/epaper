# EP-DI ePaper security cutover and release gates (2026-11-01)

**Release status: BLOCKED. No production rotation, live storage migration, Git-history rewrite, staging acceptance or deployment is evidenced by this branch.** Never treat this document or a passing PHP lint check as proof of containment.

## P0: external operator actions (must be performed on real infrastructure)

1. **Incident coordination:** preserve a forensically useful snapshot of Git and Cloudways access/audit logs; record known exposed credential types without pasting values into tickets. Freeze unreviewed deployments. Restrict repository/CDN visibility if feasible, then revoke unknown collaborators and unused deploy keys; audit PATs, CI tokens, webhook secrets, Cloudways users and SSH keys.
2. **Back up before source changes:** independently back up the live MariaDB database, application config, upload PDFs, raster media, clipping files, queued jobs, SSL and deploy configuration. Verify SHA-256 inventories and a **successful isolated restore**; record sign-off.
3. **Protect runtime media:** provision a non-web-served storage location owned by the application service account. Stage an rsync/copy of all source PDFs and rendered assets, compare file manifests, preserve file ownership, and test rendering/clipping/offline exports. Set Cloudways document root to `codebase/public_html`. Configure **both Nginx and Apache** to reject direct requests for `/storage/`, `/config/`, dotfiles, `/vendor/` and private backups. Do not rely on `.htaccess` alone.
4. **Rotate in controlled order:** replace compromised DB, Redis, RabbitMQ, Platform product/application/service tokens, app signing keys, third-party API keys and all other exposed credentials in their provider consoles. Stage dual-key/blue-green cutovers where supported, deploy new secret values out of band (runtime files or environment), test connection pooling, workers, signed image URLs, offline licences, device sessions and integrations; revoke old credentials immediately after verified cutover. Changing an app HMAC key invalidates old links.
5. **Untrack secrets and media:** integrate reviewed existing draft [security PR #1](https://github.com/indigiti/epaper/pull/1) and [storage PR #17](https://github.com/indigiti/epaper/pull/17) only *after* storage preservation acceptance. Never run a destructive Git checkout/rsync --delete against live tenant storage. Maintain new private storage mounts and include backup restoration and rollback procedures.
6. **Purge public Git history:** with all affected repositories and branch refs accounted for, take a mirror backup, use git-filter-repo to remove leaked config and customer media from **every** affected ref, coordinate a maintenance/freeze window, invalidate forks/cached clones/old tokens where possible, force-push only after operator authorization, require fresh clones and verify unreachable blobs cannot be served through GitHub/CDN. GitHub Support may need to help with cached views. History purge is NOT a substitute for rotation.
7. **Prove containment:** independently request unlisted/private page media, unsigned/expired/forged tokens, unpublished tiles and OCR, and direct `/storage/` and `/config/` URLs from unauthenticated external clients. All must fail. Verify valid published-reader flows still work, staff draft previews require authentication, and no secret or newspaper asset is tracked on any active branch.

## M2/M3/M4 release tests

- `node codebase/tests/reader-interaction-regression.mjs` (fast syntax/source invariants)
- `python3 codebase/tests/security-source-guard.py` (strict current-tree source check; intentionally fails while P0 exposure remains)
- `EPDI_READER_TEST_URL=https://<staging>/edition/<slug>/page/1 node codebase/tests/reader-live-browser.mjs` (staging browser, screenshots and accessibility; must actually execute, not skip)
- Existing `php codebase/bin/release-check.php` and GitHub workflow
- Staging parity: PHP/DB/Redis/queue versions, read/write media permissions, proxy and headers, CDN invalidation, rollback, real-device coverage, OCR and offline licence validation.

## Attestation

Record named operator, UTC timestamp, evidence links, restored asset counts, test results, key revocation confirmation, deployed commit SHA and rollback target. **All boxes stay open until real evidence is attached.**
