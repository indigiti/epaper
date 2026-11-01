# EP-DI source directory

On `cw-epaper`, the PHP application root is the **repository root** and its web document root is **`public_html/`**. The default `main` branch uses a historical nested `codebase/` layout; do not interchange their deployment paths.

| Location | Purpose |
|---|---|
| [app/](app/) | Controllers, core services, publishers, reader and clipping |
| [bin/](bin/) | Worker processes, scheduled jobs and CLI diagnostics |
| [database/](database/) | MariaDB schema and migrations |
| [config/](config/) | Templates; production credentials are private |
| [public_html/](public_html/) | Web entry points, assets and offline Reader |
| [tests/](tests/) | Application tests |
| [mobile-android/](mobile-android/) | Android reader client |
| [docs/](docs/) | Docs and historical notes |
| [release/](release/) | Unmodified historical release evidence |
| [storage/](storage/) | Historically Git-tracked runtime media; preserve on live Cloudways |
| [vendor/](vendor/) | PHP dependencies in historical checkout |

## Historical documentation

See [Cloudways deployment](DEPLOY-CLOUDWAYS.md) and [existing staging acceptance](docs/EXISTING-CLOUDWAYS-STAGING-ACCEPTANCE.md). CI source-contract success is not equivalent to browser, live deployment, or backup/restore acceptance.

Verify the currently deployed branch, source commit, web document root, and persistent runtime storage before any release. Run `php bin/release-check.php` when the installed release manifest is available; release integrity is a separate acceptance gate.

## Production safety

Do not remove or migrate live PDFs, pages or clipping media without protected persistent storage and a verified backup restoration rehearsal. Historical exposed credentials also require rotation.
