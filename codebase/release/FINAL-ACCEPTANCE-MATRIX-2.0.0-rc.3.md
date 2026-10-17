# EP-DI v2.0.0-rc.3 — Final Acceptance Matrix

This matrix is the manual production acceptance gate that complements automated `/admin/system/health` and `/admin/system/release-audit` checks.

## Mandatory automated prerequisite

- Production Readiness: `READY`
- Release Audit: `AUTOMATED PASS`
- No unresolved `ACTION_REQUIRED` or `DEGRADED` required checks
- Final package SHA-256 verified

## Manual production acceptance

### 1. Backup & restore rehearsal
- [ ] Create an on-demand Cloudways application/database backup.
- [ ] Record timestamp, deployed version and backup reference.
- [ ] Complete one restore rehearsal to a safe target/staging process.
- [ ] Verify database schema, original PDFs, generated media and configuration after restore.
- [ ] Verify scheduler/Supervisor processes after restore.

### 2. Publishing workflow
- [ ] Create a non-production test edition.
- [ ] Process successfully through render/assets/OCR/finalize.
- [ ] Exercise one controlled failure and manual/recommended reprocess path.
- [ ] Verify Processing History preserves attempts.
- [ ] Schedule publishing a few minutes ahead.
- [ ] Confirm publication occurs only after READY.
- [ ] Test Cancel Schedule / Reschedule / Publish Now where applicable.

### 3. Reader acceptance
- [ ] Desktop large viewport.
- [ ] Standard laptop viewport.
- [ ] Tablet landscape.
- [ ] Tablet portrait.
- [ ] Mobile narrow viewport.
- [ ] Page previous/next/jump/keyboard behavior.
- [ ] Zoom/fit/reset/data-saver behavior.
- [ ] Thumbnail rail behavior.
- [ ] Clipping workflow.
- [ ] Article mode / OCR selection behavior.

### 4. Encrypted Offline Vault
- [ ] Download one eligible recent edition.
- [ ] Disconnect network / airplane mode.
- [ ] Open Offline Library.
- [ ] Read first, middle and last pages.
- [ ] Verify local edition removal.
- [ ] Verify license expiry behavior.
- [ ] Verify device revocation.
- [ ] Verify logout wipes device-local vault state as designed.

### 5. Public Newsstand & Archive
- [ ] Homepage current-edition hero renders correctly.
- [ ] Recent edition rail uses THUMB assets.
- [ ] Archive date rail shows actual availability.
- [ ] Publication/city/language filters remain preserved across date changes.
- [ ] Missing THUMB displays placeholder rather than PREVIEW/HIGHRES fallback.
- [ ] Publication landing pages use THUMB card assets.
- [ ] No overlap/horizontal layout break on target breakpoints.

### 6. Publisher Console UX
- [ ] Sidebar and top bar behave across desktop/tablet/mobile.
- [ ] Editions View/Edit/Reprocess/Archive/Restore/More actions are all reachable.
- [ ] Less-common `••• More` actions remain visible when required.
- [ ] Drawers/modals trap focus and close with Escape.
- [ ] Keyboard focus is visible.
- [ ] Icon-only actions expose accessible names/tooltips.
- [ ] Tables remain operable on narrow screens.
- [ ] Submit actions show immediate busy feedback and resist duplicate submission.

### 7. SEO & discovery
- [ ] `/robots.txt` correct for production domain.
- [ ] `/sitemap.xml` accessible.
- [ ] `/news-sitemap.xml` contains only eligible recent semantic stories.
- [ ] One published Article has canonical URL and expected hreflang output.
- [ ] NewsArticle/Organization/author structured data validates against live HTML.
- [ ] Trust pages intended for indexing contain approved publisher content.

### 8. Recovery & observability
- [ ] Error Center correlates request/job/edition failure.
- [ ] RabbitMQ queues and Supervisor consumer counts are healthy.
- [ ] Scheduler heartbeat is current.
- [ ] Controlled scheduler restart/catch-up test passes.
- [ ] Redis/cache observability is healthy and no unexpected evictions occur.
- [ ] Storage free-space and log/temp-file checks are acceptable.
- [ ] One controlled worker/service degradation produces the expected readiness signal.

## Stable release decision

Promote to **EP-DI v2.0.0 Production Stable** only when all mandatory items above are complete and no unresolved critical/high defect remains.
