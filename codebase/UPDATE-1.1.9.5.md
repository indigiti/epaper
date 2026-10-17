# ePaper SaaS v1.1.9.5 — Original Layout Responsive Auth Reset

## Purpose
Fix live `/login` layout instability by isolating authentication styling from the cumulative v1.1.9.1–v1.1.9.4 rules in `app.css`.

## Changes
- Adds a dedicated post-cascade stylesheet: `/assets/auth-v1.1.9.5.css`.
- Rebuilds the desktop login from the original City ePaper / Cloudways split-layout proportions.
- Restores a visually distinct centered form card on the right.
- Keeps publisher logo/tagline, DIGITAL EPAPER label, two feature rows, and rotating quote on the left.
- `Searchable Archive` remains removed.
- Uses standard system font weights (400/600/700/800) instead of interpolated/non-standard font weights.
- Adds separate tablet and phone layouts; mobile shows publisher branding + sign-in first, then the editorial story.
- Removes dependency on fixed-height auth patches and competing legacy auth selectors.
- Adds a new unique stylesheet filename and v1.1.9.5 asset namespace to defeat stale auth CSS caches.

## No functional changes
Authentication POST handling, MFA, Digiti Platform background verification, same-domain subscription flow, Reader, clipping, Source Page QR, account/admin CRUD, Redis prefetch, and processing are unchanged.
