# Final Re-audit — ePaper SaaS v1.1.3

Release state: **TESTED**

## Scope
v1.1.3 consolidates the live Reader issues reported after v1.1.2.x:
- bottom pager drifting below the visible browser area,
- Reader document scrolling instead of the newspaper canvas,
- page thumbnails contributing to Reader height,
- Pages browser not being directly toggleable beside Search,
- no explicit slow-network / low-data media policy.

The release keeps City ePaper v2.2.9.1 as the Reader behavior baseline while retaining the ePaper SaaS multi-tenant/RabbitMQ/Supervisor architecture.

## Regression inventory
- Declared routes: **68**
- Capabilities: **128**
- Settings: **46**
- Tenant-owned SQL statements audited: **178**
- Missing baseline routes: **0**
- Missing baseline capabilities: **0**
- Missing baseline settings: **0**

## Reader viewport verification
Headless Chromium was run at **1440×900** and **390×844**.

Verified on both sizes:
- no browser/document scrolling,
- bottom pager remains inside viewport,
- newspaper stage is bounded between Reader header and pager,
- no Reader JavaScript page errors,
- Low Data mode persists and updates the effective policy,
- High Quality mode persists and updates the effective policy.

Desktop additionally verified:
- Pages browser opens by default on first desktop visit,
- page thumbnails scroll independently,
- closing Pages returns its width to the newspaper canvas.

Mobile additionally verified:
- Pages defaults closed,
- Pages opens as a drawer,
- opening Data Saver closes the Pages drawer to prevent control overlap.

## Data-quality policy
### Auto
- uses Network Information hints when available,
- treats Save-Data, slow-2g, 2g and 3g as low-data conditions,
- preview-first delivery,
- high-resolution promotion only on normal connections.

### Low Data
- PREVIEW WebP is the primary page image,
- adjacent image prefetch is disabled,
- Deep Zoom is deferred until a higher zoom threshold,
- existing HIGHRES download is not required for normal fitted reading.

### High Quality
- HIGHRES WebP is promoted immediately,
- adjacent high-resolution page prefetch is enabled when Reader prefetch is enabled.

## Security/regression preservation
- v1.1.2.1 authentication `private, no-store` boundary retained,
- CSRF token rotation after successful authentication retained,
- expired sign-in page recovery retained,
- tenant SQL guard PASS,
- RabbitMQ/Supervisor/outbox guards PASS,
- Deep Zoom/media routes retained,
- Clip Mode/Smart Article/account/admin functionality retained,
- database schema unchanged.

## Static/runtime checks
- 51 PHP files linted successfully.
- `app.js` syntax PASS.
- `reader.js` syntax PASS.
- `service-worker.js` syntax PASS.
- current build guard PASS.
- tenant-query guard PASS.
- Reader parity PASS.
- City compatibility/parity manifests PASS.
- release-check PASS.

## Live verification still required
The release should move from TESTED to VERIFIED only after:
1. real mobile two-finger pinch/pan on `epaper.digiti.in`,
2. real desktop zoom + pan with pager permanently reachable,
3. one actual slow-network test confirming PREVIEW is used without automatic HIGHRES prefetch in Low Data mode,
4. one normal/high-quality test confirming HIGHRES promotion and Deep Zoom.
