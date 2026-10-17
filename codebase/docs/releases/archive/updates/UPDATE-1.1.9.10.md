# ePaper SaaS v1.1.9.10 — Single-Flow Login Gap Fix

## Purpose
Fix the live authentication page where a large blank vertical zone could remain between the security note and rotating editorial quote.

## Root cause
The quote was a sibling of the isolated form wrapper. On some live/stale/global CSS combinations the wrapper could retain excess block height, pushing the quote to the bottom even though the card itself used content-fit sizing.

## Fix
- Move the editorial quote inside the same `.auth-fit-panel` content flow immediately after `.auth-fit-security`.
- Make `.auth-fit-form` `display: contents` for the v1.1.9.10 surface so the wrapper cannot reserve any vertical space.
- Keep the outer card content-sized with no minimum/fixed height.
- Add hostile-CSS browser regression tests that intentionally try to force 900–1300px heights.
- Bump auth CSS/service-worker/runtime asset version to 1.1.9.10 to defeat stale asset caches.

## Preserved
No database migration. Source Page QR, clipping services, Settings normalization, Smart Sharing, Guest Clipping, Reader Redis/prefetch, Account/Admin CRUD, same-domain subscription and rotating quote pool remain unchanged.
