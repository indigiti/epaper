# ePaper Cloudways staging SOURCE CANDIDATE — NOT PRODUCTION READY

Audited origin: `c2b1836dbee7fea627d23860b53091190df60f8a`, subtree `codebase/`.
Built on 2026-10-18 for **isolated Cloudways staging validation**. Branch: `staging/cloudways-epaper-20261018`.

## Important
- This is a sanitized source candidate. Runtime/private `config/config.php`, `config/runtime.php`, lock files, media, clipped articles, logs, Redis files, PDF uploads, `public_html/uploads` and ePaper's vendored libraries are **not packaged**.
- Never directly change the currently deployed `cw-platform`/`cw-epaper` Git checkout from this branch without a separate verified restore and private persistent-data migration. Git can delete files when replacing tracked paths.
- Cloudways Git often deploys to `public_html/` by default. This branch is an **application-root** tree containing `app/`, `bin/`, `config/`, `database/`, `public_html/`, `storage/`. Its Apache document root must point to the nested `public_html/`, and private directories must not be HTTP accessible. Misconfigured destination is a release-blocking finding.
- Set private staging-only config, isolated MariaDB/Redis and HMAC keys, persistent writable storage and uploads, cron/worker paths before exercising staging. Never store keys or sensitive backups in Git.
- ePaper: install Composer dependencies from `composer.lock` under the application root using an audited installation process. Do not deploy this source-only branch as a fully assembled artifact.
- Confirm backups and isolated restores separately for Platform and ePaper; run source CI plus real Cloudways staging HTTP, two-tenant suspension, signed event delivery, media checksum, Reader/PDF/clipping and rollback gates.
- Prior git history contains credential exposure; source sanitization is not key rotation or all-ref history cleanup.
- No staging or production deployment was executed when this branch was created.

See audited runbook in `platform/docs/architecture/STAGING-PERFORMANCE-DEPLOYMENT-GATES.md` at the above origin commit. Track blockers in issues #2, #21, #22, #25 and draft PR #23.
