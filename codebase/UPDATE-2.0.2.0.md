# Update to EP-DI v2.0.2.0

Base release: **v2.0.1.9**.

1. Back up application files and database.
2. Overlay the v2.0.2.0 patch, or deploy the full-source package.
3. Clear/reload PHP OPcache if your deployment keeps application code cached.
4. No database migration is required.
5. Open Admin → Settings → Reader and choose the default Reader View and spread policy.
6. Verify an edition on desktop, tablet and mobile in Flip, Page and Continuous modes.
7. Confirm zoom/clip/Smart Article temporarily use the Page surface and return to Flip after Fit Page/reset.
8. Verify cover → 2–3 spreads, the odd final spread, low-power/reduced-motion swipe fallback, and double-tap zoom on both sides of a desktop spread.
9. Verify Service Worker namespace `epdi-v2.0.2.0`.

Rollback: restore the v2.0.1.9 application files. No schema rollback is required.
