# Final Re-audit — ePaper SaaS v1.1.4.1

## Requested behavior
- Branded clipping artwork no longer prints expiry details.
- The freed footer area contains a QR code that opens the exact source edition page.
- The clipping showcase no longer displays the expiry timestamp/window in its title block; it shows Source Page context instead.
- The separate share-page `Scan to open` QR remains unchanged and continues to open the clipping page.
- Seven-day expiry remains enforced server-side and is still communicated during clipping creation.

## Regression results
- Full existing release check: PASS.
- Tenant query guard: PASS, 180 tenant SQL statements checked.
- City Reader parity: PASS.
- Routes/capabilities/settings parity: no removals.
- QR matrix port matches the previously validated browser QR encoder exactly for a representative source URL.
- Source QR was decoded back to the exact source page URL in a QR decode smoke test: PASS.
- Existing branded/share assets are versioned so old clips regenerate rather than reusing artwork that contains expiry text.

## Release state
TESTED. Live Cloudways Imagick compositing and phone-camera scan should be confirmed after deployment before promoting to VERIFIED.
