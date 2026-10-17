# Development and code-placement workflow

## Where new work goes

- PHP application behavior: `app/Core/` for cross-cutting primitives or `app/Services/` for domain services.
- Public request routing: `app/App.php` until a separately verified controller extraction takes place.
- Browser-facing assets: `public_html/assets/` and version-aware PWA handling in `public_html/service-worker.js`.
- Database upgrades: additive, versioned SQL under `database/` with migration checks.
- CLI/workers: `bin/`, retaining existing invocation paths relied on by cron/Supervisor.
- Active procedures: `docs/operations/`; threat models: `docs/security/`; test guides: `docs/testing/`; architecture decisions: `docs/architecture/`.
- Versioned release notes/checksums: keep in their existing pinned location until a release-integrity migration.

## Before opening a PR

1. Confirm your base branch and any dependent draft security PR.
2. Ensure no production keys, `.env`, installer locks or customer content are staged.
3. Run `cd codebase && php bin/release-check.php`, and `php tests/repository-layout-static.php`.
4. Run JavaScript syntax checks and the GitHub root workflow.
5. Test tenant boundaries, existing URLs, reader/clip paths, worker restart semantics and browser CacheStorage when applicable.
6. Do not claim live readiness from only static/source tests.

## Deployment invariant

Live installation checkout, PHP-FPM, Supervisor and cron must all resolve the same **external private configuration**. `public_html/` is the webroot. No renaming/moving storage or current CLI entry points without a verified backup, a staging rehearsal and a rollback path.

**Never commit generated editions, original PDFs, user clippings, caches, logs or application secrets.** Legacy tracked copies remain until safe Cloudways backup/restore and content migration are complete.
