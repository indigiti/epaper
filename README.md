# EP-DI ePaper

Multi-tenant digital newspaper publishing, ePaper reading, OCR, clipping, archives and offline reading.

> **Release note:** The current deployed application is not certified by this repository alone. Security remediation remains in draft [PR #1](https://github.com/indigiti/epaper/pull/1). [Security issue #2](https://github.com/indigiti/epaper/issues/2) requires live staging, verified recovery backup, credential rotation and Git-history cleanup before production promotion.

## Start here

- [Documentation and functional area guide](codebase/docs/README.md)
- [Application architecture and module ownership](codebase/docs/architecture/MODULE-MAP.md)
- [Developer workflow and safety boundaries](codebase/docs/development/README.md)
- [Operational release and recovery documents](codebase/docs/operations/README.md)
- [Slim ZIP artifact and Cloudways staging promotion gate](codebase/docs/operations/SLIM-RELEASE-CLOUDWAYS-PROMOTION-2026-10-17.md)
- [Historical releases and immutable manifest policy](codebase/docs/releases/README.md)
- [Security notes and remediation work](codebase/docs/security/SECURITY-CUTOVER-RUNBOOK.md)

## Repository layout

```text
.github/workflows/       Active GitHub CI (root)
codebase/
  app/                   PHP entry controller, Core and Services
  bin/                   Production CLI commands, workers and diagnostics
  config/                Example config; real credentials must not be tracked
  database/              Database schema and versioned migrations
  docs/                  Active architecture, security, operational and QA docs
  mobile-android/        Android reader wrapper project
  public_html/           Only intended public web document root
  tests/                 Regression tests and fixtures
  release/               Historical versioned release evidence
  storage/               Placeholder-only in slim PR; persistent runtime data must remain external
  vendor/                Legacy tracked Composer dependencies
  VERSION                Product version consumed by release checks
  SHA256SUMS             Historical v2.0.2.1 integrity record
```

The runtime webroot is **`codebase/public_html`**, not the GitHub repository root. Do not move `app/`, `bin/`, `config/`, `database/`, `storage/` or `public_html/` without updating the deployment/runtime paths and running end-to-end acceptance.

## Safe verification

```bash
cd codebase
composer install --no-dev --prefer-dist
php bin/release-check.php
php tests/repository-layout-static.php
```

No live passwords, tokens or backups should be committed, posted in issues or sent through PR comments. Root CI verifies source; live Cloudways/Redis/RabbitMQ/Platform services require a separate operator-led staging acceptance.
