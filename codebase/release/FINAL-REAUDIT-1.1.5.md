# Final Re-audit — ePaper SaaS v1.1.5

Release state: **TESTED**

## Mandatory baseline
The user-supplied **ePaper SaaS v1.1.4.1 — Source Page QR Hotfix** was treated as the protected baseline for every file available in the hotfix package.

### Source Page QR preservation
- `app/Services/QrCode.php`: exact SHA-256 match to uploaded v1.1.4.1.
- `app/Services/ClipService.php`: exact SHA-256 match to uploaded v1.1.4.1.
- `BRANDING_VERSION = source-qr-v1` retained.
- source-page QR still resolves to the exact edition/page URL.
- clipping showcase Source Page block retained.
- separate Scan to open clipping QR retained.
- seven-day clipping expiry remains server-enforced while visible clipping artwork/showcase expiry remains removed per v1.1.4.1.
- QR decode smoke test: PASS.

## Brand & Identity
`/admin/settings/brand` is now a dedicated multipart upload manager rather than a URL-entry form.

Implemented:
- Primary Logo upload / preview / replace / remove.
- Dark Logo upload / preview / replace / remove.
- Square Brand Mark upload / preview / replace / remove.
- Favicon upload / preview / replace / remove.
- PNG/JPEG/WebP MIME validation.
- 8 MB maximum per asset.
- image dimension validation.
- tenant-isolated public asset directory under `public_html/uploads/branding/{tenant_guid}`.
- Imagick optimization when available.
- Square Mark derivative generation: favicon 32, Apple Touch 180, PWA 192, PWA 512.
- dark logo switching in the existing dark Admin shell.
- Apple Touch icon output in page head.
- improved validation error page includes Request GUID.

No raw filesystem/CDN URL knowledge is required from the administrator.

## CRUD Foundation
Admin:
- Publications: Create, Edit, Enable/Disable, Permanent Delete only when unused/non-default.
- Highlights: Create, Edit, Enable/Disable, Delete.
- Cities/Languages/Sections: Create, Edit, Enable/Disable, Delete-if-unused safeguards.
- Editions: Create, metadata Edit, Archive/Restore. Destructive deletion intentionally excluded to retain processing/audit evidence.
- Subscribers: ePaper-local Enable/Disable. Identity and membership remain authoritative in Digiti Platform.

Account:
- Saved Articles: Edit title, Remove, Clear all.
- Bookmarks: Edit note, Remove, Clear all.
- Clips: Edit title/Private-Public visibility, Delete generated clipping, Clear all.
- History: Remove edition, Clear all.
- Offline: Remove edition record, Clear all.
- Notifications: Mark read/Delete for user-specific notifications; Clear all user-specific notifications. Shared publisher notifications remain protected.

Immutable by design:
- Audit events.
- Error history.
- Processing history/jobs (retry/recovery remains available).
- Platform billing/invoices/membership identity data.

## Security and data integrity
- Mutations are POST-only.
- CSRF verification remains enforced by existing Admin/Account routing.
- destructive actions have explicit UI confirmation.
- new DELETE statements are tenant-scoped.
- default publications cannot be disabled/deleted.
- referenced publication/city/language records cannot be deleted.
- account mutations require the authenticated user's `platform_user_guid`.
- brand filenames are deterministic and tenant directory is derived from RequestContext, not user-supplied paths.
- SVG upload is intentionally excluded.

## Tests performed in packaging environment
- PHP syntax: PASS.
- `crud.js` syntax: PASS.
- service worker syntax: PASS.
- v1.1.5 build guard: PASS.
- v1.1.4.1 QR critical file hash guard: PASS.
- source QR decode smoke: PASS.
- headless Chromium: brand color sync PASS.
- headless Chromium: local brand image preview PASS.
- headless Chromium: dark logo switching PASS.
- headless Chromium: action menu close behavior PASS.
- headless Chromium: destructive-action cancel blocks submit PASS.
- ZIP integrity and SHA256SUMS verification: PASS after packaging.

## Live verification required before VERIFIED
1. Update `/admin/settings/brand` text-only values.
2. Upload Primary Logo, Dark Logo, Square Mark and Favicon on Cloudways.
3. Refresh Reader/Admin/Clip page and confirm managed asset URLs load.
4. Confirm Source Page QR from v1.1.4.1 still scans to exact source page.
5. Exercise Edit/Disable/Delete-if-unused for a disposable Publication, Highlight and Master-data record.
6. Edit/archive/restore a disposable Draft edition.
7. Test Account Library edits/removals as a non-admin reader.
8. Confirm Audit/Error/Processing history cannot be destructively deleted.

Only after these live checks should v1.1.5 be promoted from TESTED to VERIFIED.
