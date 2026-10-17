# EP-DI v2.0.0-beta.1 — Publisher Console & Reader Navigation Experience

## Release intent

Beta.1 is the first visual/product-experience milestone on top of the verified alpha.2.3 Runtime & Publishing Engine. It deliberately does **not** replace the working MariaDB/Redis/RabbitMQ/Supervisor foundation and requires **no database migration**.

## Implemented

### Publisher Console shell
- New isolated `admin-beta.css` design system instead of adding more global page-specific patches.
- Calmer enterprise density, restrained tenant accent, refined card/border hierarchy and responsive shell.
- Canonical sidebar IA: Overview, Content, Discovery, Audience, Operations and System.
- Header adds quick Diagnostics and Create actions while retaining command palette, theme toggle and account controls.
- Tablet/mobile sidebar behavior preserved; controls meet stronger touch/focus treatment.

### Operational dashboard
- Premium Publisher Operations header + system status card.
- KPI row with descriptive context.
- Attention Center converts runtime telemetry into publisher actions.
- Common Tasks module for upload, processing, Reader Navigation and Error Center.
- Recent Editions retained on the same low-query runtime foundation.

### Reader Navigation experience
- Scope-first workspace for Global / Publication / City / Language navigation.
- Responsive Desktop / Tablet / Mobile compiled-draft preview.
- Compact item rows for Primary, Quick, Breaking/Live and Trending.
- Accessible move-up/down ordering retained (no inaccessible drag-only dependency).
- Collapsible Add Navigation Item workflow.
- Draft/publish/version-history architecture unchanged: public runtime remains Redis → compiled file → fallback, normally 0 DB reads.

### Audience / Plans & Entitlements
- New `/admin/plans` capability console.
- Effective Digiti Platform entitlements grouped into Reader, Experience, Offline & Export and Access.
- Commercial state is intentionally read-only inside EP-DI; Digiti Platform remains the billing/entitlement control plane.
- Legacy `/admin/account` redirects to the new capability view.

### Processing / Diagnostics / Error Center
- Processing Center receives the new console hierarchy without changing RabbitMQ/Supervisor behavior.
- Error Center now includes incident counts, 24-hour volume and distinct request-chain summary derived from the existing result set (no extra SQL).
- System Health/Infrastructure retain alpha.2.3 Worker Doctor and heartbeat behavior.

### Performance work retained / improved
- No Reader/Home/Archive N+1 regressions.
- Analytics counters consolidated from four independent SQL calls into one SQL round trip with scalar subqueries.
- Redis dashboard/runtime snapshots retained.
- Admin assets are route-specific and are not added to the public PWA install shell.

## Deployment

1. Keep the last verified alpha.2.3 package as rollback.
2. Upload/extract beta.1 over the application root.
3. **Do not run any SQL migration** for beta.1.
4. Open `/admin`, `/admin/navigation`, `/admin/processing`, `/admin/plans`, `/admin/system/health` and `/admin/errors`.
5. Hard refresh once if a browser still holds an earlier admin stylesheet; build-versioned assets and the service worker are aligned to `2.0.0-beta.1`.

## Release boundary

The encrypted rolling 7-day PWA Offline Vault is **not** part of beta.1. It remains the next Reader/PWA milestone. The current legacy offline media compatibility path remains until the vault is implemented and security-tested.
