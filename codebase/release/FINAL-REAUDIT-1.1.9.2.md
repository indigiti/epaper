# Final Re-audit — ePaper SaaS v1.1.9.2

## Scope
Auth-layout-only cumulative hotfix on v1.1.9.1.

## Confirmed
- Removed the Searchable Archive value row and its description.
- Kept Today’s ePaper and Clipping & Sharing Tools.
- Rebalanced left/right desktop columns.
- Removed fixed desktop auth height that could clip the sign-in form at real browser zoom/viewport combinations.
- Full sign-in controls remain inside the card at 1440×900, 1366×768 and 1280×720.
- Mobile remains sign-in-first and stacked.
- Rotating quotes remain enabled.
- Publisher branding remains enabled.
- Authentication behavior is unchanged.
- Digiti Platform remains backend/control-plane only; no public Platform URL was added.
- v1.1.4.1 Source Page QR / ClipService protected hashes remain unchanged.

## Release state
**TESTED** — live Cloudways verification still required before marking VERIFIED.
