# ePaper SaaS v1.1.9.1 — Compact Branded Login Layout

This is a presentation-only hotfix on top of v1.1.9.

## What changes

- Desktop auth shell is changed from a tall page-like composition to a compact landscape card.
- Maximum desktop card target is 1040 × 620 px, subject to the available viewport.
- Hero typography and vertical spacing are tightened.
- The three product benefit descriptions remain visible in compact rows.
- The rotating quote remains inside the visible card instead of being pushed below the primary viewport.
- Sign-in form remains vertically centered and uses smaller, proportionate controls.
- Publisher logo/tagline behavior and all 12 rotating quotes are unchanged.
- Mobile remains a natural stacked layout with the sign-in form first.

## Not changed

- Authentication POST/callback/MFA/session/CSRF behavior.
- Same-domain subscription behavior.
- Reader, Redis prefetch/cache, clipping, Source Page QR, account/admin CRUD, RabbitMQ or Supervisor.
- Database schema.
