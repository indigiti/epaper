# ePaper SaaS v1.0.0 — Build Manifest

## Release identity

- Product: **ePaper SaaS**
- Version: **1.0.0**
- Product key: `EPAPER`
- Application key: `city_epaper`
- Required platform: Digiti Platform v1.0.0+
- Deployment model: fresh Cloudways Custom PHP installation
- Webroot: `public_html/public_html`

## Inventory

- MariaDB tables: **28**
- Route manifest entries: **46**
- Tenant/mixed tables covered by tenant query guard: **26**
- Application tenant SQL statements statically checked: **97**
- Analytics event catalogue entries: see `ANALYTICS-EVENTS-1.0.0.json`
- Settings inventory: see `SETTINGS-MANIFEST-1.0.0.json`
- Capability inventory: see `CAPABILITIES-1.0.0.json`

## Runtime components

- server-rendered public/publisher/account shells
- multi-tenant DomainResolver / RequestContext
- Digiti Platform client and OAuth PKCE identity
- tenant-aware MariaDB repositories/queries
- Redis domain/entitlement/rate-limit/lock cache
- publisher upload/processing/publishing workflow
- PDF rendering, image previews, Deep Zoom tiles, OCR/hOCR
- premium responsive Reader
- clipping / Smart Article / TTS / offline / PWA
- account Library
- Audit / Error Center / Health
- CLI worker / scheduler / diagnostics

## Security/release manifests

- `ROUTES-1.0.0.json`
- `CAPABILITIES-1.0.0.json`
- `SETTINGS-MANIFEST-1.0.0.json`
- `ANALYTICS-EVENTS-1.0.0.json`
- `TENANT-DATA-MANIFEST-1.0.0.json`
- `PROTECTED-FILE-HASHES-1.0.0.json`

## Schema policy

This is a fresh schema. It is **not** an upgrade/migration schema for any legacy City ePaper installation.
