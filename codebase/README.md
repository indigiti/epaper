# EP-DI · ePaper publishing platform

Multi-tenant newspaper publishing and reading with PDF ingestion, processing workers, OCR, WebP pages, clipping, private reader libraries and offline access.

**Current source version:** [VERSION](VERSION). **Deployment status:** the main Cloudways application is **not** certified by this repository alone. Proposed cleanup and security work are still in draft PRs, pending host-side validation and credential remediation.

## Explore the code

| Area | Contents |
|---|---|
| [app/](app/) | HTTP controller, Core, services and platform integration |
| [public_html/](public_html/) | Public webroot, JavaScript, CSS and service worker |
| [bin/](bin/) | CLI, schedulers, workers, verification and release tooling |
| [database/](database/) | Current schema and sequential migrations |
| [config/](config/) | Example settings only; production config stays private |
| [mobile-android/](mobile-android/) | Android reader application |
| [tests/](tests/) | Release, security and feature regression tests |
| [docs/](docs/) | Current documentation, architecture and operational guides |
| [docs/releases/archive/](docs/releases/archive/) | Grouped legacy release documentation |
| [release/](release/) | Original historical release snapshots and parity evidence |

**Historical filenames:** Start at the [old-to-new document map](docs/releases/LEGACY-ROOT-INDEX.md). A [copy of the former README](docs/releases/archive/references/LEGACY-PROJECT-README.md) is retained for historical background.

## Safe development checks

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php bin/release-check.php
php tests/repository-layout-static.php
```

Only `public_html/` is intended to be web accessible. Runtime storage and public uploads are not deployable source code; the cleanup branch must preserve them outside any replaceable checkout.

## Release and security

- [Documentation index](docs/README.md) and [component map](docs/architecture/MODULE-MAP.md)
- [Cloudways staging release and rollback](docs/operations/SLIM-RELEASE-CLOUDWAYS-PROMOTION-2026-10-17.md)
- [Operational security cutover](docs/security/SECURITY-CUTOVER-RUNBOOK.md)
- [Legacy checksum policy](docs/releases/README.md)

**Important:** `SHA256SUMS` and the original versioned manifests document an older layout, not the proposed slim ZIP. Do not execute Git checkouts, reset, `rsync --delete`, or a ZIP overlay on a Cloudways application containing historically Git-tracked media. Green CI does not verify a live deployment.
