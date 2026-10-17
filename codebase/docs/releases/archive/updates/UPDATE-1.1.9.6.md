# ePaper SaaS v1.1.9.6 — Simple Glass Login Reset

This hotfix replaces the v1.1.9.x split editorial login shell with a single centered responsive glass login card inspired by the supplied light/cloud login reference.

## Changes

- Removes the split editorial/auth layout from the authentication renderer.
- Adds a new dedicated `/assets/auth-v1.1.9.6.css` stylesheet with unique selectors.
- Uses publisher logo/mark/name at the top-left of the page.
- Uses a centered 500px maximum login card on desktop and fluid card on mobile.
- Uses a light sky/editorial gradient background with CSS-only soft cloud/orbit decoration; no background image file is stored.
- Keeps Email, Password, optional Authenticator code and Sign in behavior unchanged.
- Keeps the rotating publisher quote as a low-priority line below the card.
- Bumps Service Worker and asset query versions to 1.1.9.6 so older auth CSS cannot be reused from cache.
- Adds `data-auth-version="1.1.9.6"` for live DOM verification.

## Preserved

No database migration. No queue/worker changes. Digiti Platform remains background-only. Source Page QR and ClipService are not packaged by this hotfix.
