# EP-DI source directory

The ePaper PHP application root is `codebase/`. Its web document root is **`public_html/`**, not the entire Git repository.

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

The prior `codebase/` root contained ~170 redundant or older versioned documents. Use the [old filename index](docs/releases/LEGACY-ROOT-INDEX.md) to locate every document and its original Git SHA, and the [archive categories](docs/releases/archive/README.md) to browse past releases. The previous [source README](docs/releases/archive/references/LEGACY-PROJECT-README.md) is retained byte-for-byte.

**Pinned current release records** and historical `SHA256SUMS` remain at their original paths. Run `php bin/release-check.php` to verify the installed source (this is not production deployment acceptance).

## Production safety

This documentation-only refactor neither deletes nor migrates customer PDFs, pages and clips. Do not merge the separate 4,684-file runtime-storage deletion PR into production without protected persistent storage and a verified Cloudways/DigiOps staging rehearsal. Historical exposed credentials also require rotation.
