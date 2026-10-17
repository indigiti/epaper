# ePaper SaaS v1.1.0 — Release Test Results

State: **TESTED**

## Executed in build environment

- PHP syntax: all packaged PHP files PASS
- JavaScript syntax: app.js + reader.js PASS
- UUIDv7 format and BINARY(16) roundtrip PASS
- Analytics frontend/backend registry parity PASS
- Route manifest protected families PASS
- Capability manifest protected families PASS
- Settings / Reader / Processing / City compatibility JSON validation PASS
- Tenant query guard: all application SQL touching tenant-owned tables carries `tenant_guid` PASS
- Reader parity static guard: Fit Page/Width, pinch, pan, scroll zoom, History API, prefetch, Clip, Smart Article, offline and progress hooks PASS
- Reader HIGHRES / preview / thumbnail / Deep Zoom delivery asset policy: WebP PASS
- RabbitMQ topology / worker lock/requeue / outbox static guards PASS
- Service Worker + v1.1.0 asset cache alignment PASS
- Schema duplicate-table guard PASS

## Headless Chromium Reader smoke

Executed against the packaged v1.1.0 Reader code in Chromium:

- desktop initial Fit Page PASS
- desktop zoom beyond fit PASS
- desktop wheel/trackpad-style pan to lower content PASS
- desktop top edge recovery PASS
- keyboard seamless next-page navigation PASS
- Clip Mode activate + Esc cancel PASS
- mobile Fit Page below legacy 0.5 floor PASS
- mobile synthetic two-pointer pinch-in PASS
- mobile pinch-out PASS
- mobile return to fit-relative minimum PASS

12/12 checks PASS. Synthetic pointer tests validate the actual packaged pointer-event handlers; physical-device gesture QA remains required for VERIFIED.

## Not claimable from packaging environment

The build environment cannot perform the live Cloudways end-to-end checks against the user's production MariaDB/Redis/RabbitMQ/Supervisor/Platform data or physical touch gestures. Promote to VERIFIED only after the live Release Audit, Infrastructure probe, one real PDF processing run and mobile/desktop Reader regression pass.
