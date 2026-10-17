# EP-DI ePaper

Multi-tenant newspaper publishing, PDF rendering, OCR, WebP page delivery, article clipping, reader accounts, archives, and offline reading.

> **Repository status (17 October 2026):** The default `main` branch retains the historical deployment layout. The reorganized source tree and slim packaging are available for review in [cleanup PR #18](https://github.com/indigiti/epaper/pull/18) and [storage PR #17](https://github.com/indigiti/epaper/pull/17), but are **not yet merged or approved for Cloudways production**. This landing page is documentation-only and does not change the application.

## Find what you need

| Goal | Location |
|---|---|
| PHP application, controllers and services | [`codebase/app/`](codebase/app/) |
| Browser entry points, styles and Reader assets | [`codebase/public_html/`](codebase/public_html/) |
| CLI tasks, PDF workers and scheduler | [`codebase/bin/`](codebase/bin/) |
| MariaDB schema and upgrades | [`codebase/database/`](codebase/database/) |
| Regression, security and Reader tests | [`codebase/tests/`](codebase/tests/) |
| Android reader | [`codebase/mobile-android/`](codebase/mobile-android/) |
| Historical releases and parity evidence | [`codebase/release/`](codebase/release/) |
| Cloudways installation/upgrade notes | [`codebase/DEPLOY-CLOUDWAYS.md`](codebase/DEPLOY-CLOUDWAYS.md) |
| Full application source documentation | [`codebase/README.md`](codebase/README.md) |

The PHP application root is **`codebase/`**. The intended public web document root is **`codebase/public_html/`**. Database secrets, customer PDFs, generated pages and clipping data must remain protected from source deployment.

## Clean organization (draft, not deployed)

The [cleaned branch](https://github.com/indigiti/epaper/tree/refactor/archive-legacy-release-docs-20261017) organizes legacy release documents under `codebase/docs/releases/archive/`, preserves an [original filename map](https://github.com/indigiti/epaper/blob/refactor/archive-legacy-release-docs-20261017/codebase/docs/releases/LEGACY-ROOT-INDEX.md), and reduces `codebase/` from 209 to 39 top-level entries.

The proposed storage cleanup untracks 4,684 runtime files, **but those files may still be used by production**. Never deploy that deletion-based Git change onto an existing Cloudways checkout without a verified persistent-data migration.

## Release and security controls

- [Security stabilization PR #1](https://github.com/indigiti/epaper/pull/1)
- [Documentation/layout PR #15](https://github.com/indigiti/epaper/pull/15)
- [Runtime storage cleanup PR #17](https://github.com/indigiti/epaper/pull/17)
- [Historical document organization PR #18](https://github.com/indigiti/epaper/pull/18)
- [Active security incident #2](https://github.com/indigiti/epaper/issues/2)

**Do not treat a successful source CI run or a Cloudways one-touch backup as production promotion approval.** Known historical credentials must be revoked and rotated, real Cloudways/DigiOps deletion behavior must be rehearsed against isolated staging, and Reader/PDF/clipping/data integrity must be accepted before applying the stacked PRs to a deployed application.

This README is a GitHub navigation improvement only: it contains no credential material, runtime changes or Git history rewrite.
