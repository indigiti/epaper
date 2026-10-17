# EP-DI ePaper

Multi-tenant newspaper publishing, PDF rendering, OCR, WebP page delivery, article clipping, reader accounts, archives, and offline reading.

> **Repository status (17 October 2026):** The default `main` branch now contains the safe documentation-only reorganization from [PR #20](https://github.com/indigiti/epaper/pull/20). Historical notes were archived with original Git hashes verified. **Live media/PDFs and operational PHP code were not removed or changed.** The separate source slimming and security remediation remain unmerged.

## Two independently deployed applications

| Module | Purpose | Cloudways document root | Release status |
|---|---|---|---|
| [`codebase/`](codebase/) | ePaper SaaS publishing, Reader, OCR, PDFs and clippings | `codebase/public_html/` | Existing product; security/deployment gates remain open |
| [`platform/`](platform/) | Digiti shared SaaS control plane: identity, tenancy, billing, domains and platform operations | `platform/public_html/` | New v1.0.2 source; multilayer migration in [draft PR #23](https://github.com/indigiti/epaper/pull/23), NOT deployment-approved |

The two applications must not share the same PHP webroot or database schema. Their private config, runtime storage, cron/worker deployment paths and rollback procedures must be independently validated. See [Platform layer boundaries](platform/docs/architecture/MULTILAYER-FOUNDATION.md), [Platform audit issue #22](https://github.com/indigiti/epaper/issues/22), and [DigiOps blocker #21](https://github.com/indigiti/epaper/issues/21).

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

## Clean organization now on main

The merged [documentation cleanup](https://github.com/indigiti/epaper/pull/20) organizes historical release documents under [`codebase/docs/releases/archive/`](codebase/docs/releases/archive/), preserves an [original filename map](codebase/docs/releases/LEGACY-ROOT-INDEX.md), and reduces `codebase/` from 207 to **38 top-level entries**.

The separate [draft storage cleanup](https://github.com/indigiti/epaper/pull/17) would untrack 4,684 runtime files, **but those files may still be used by production**. Never deploy that deletion-based Git change onto an existing Cloudways checkout without a verified persistent-data migration.

## Release and security controls

- [Security stabilization PR #1](https://github.com/indigiti/epaper/pull/1)
- [Documentation/layout PR #15](https://github.com/indigiti/epaper/pull/15)
- [Runtime storage cleanup PR #17](https://github.com/indigiti/epaper/pull/17)
- [Historical document organization PR #18](https://github.com/indigiti/epaper/pull/18)
- [Active security incident #2](https://github.com/indigiti/epaper/issues/2)

**Do not treat a successful source CI run or a Cloudways one-touch backup as production promotion approval.** Known historical credentials must be revoked and rotated, real Cloudways/DigiOps deletion behavior must be rehearsed against isolated staging, and Reader/PDF/clipping/data integrity must be accepted before applying the stacked PRs to a deployed application.

This README describes the merged documentation-only cleanup; it does not authorize deployment, credential rotation or deletion of runtime media.
