# ePaper SaaS v1.1.9 — Final Re-audit

Release state: **TESTED**

## Scope
v1.1.9 replaces the generic authentication presentation with a tenant-branded editorial login experience while leaving authentication mechanics unchanged.

## Branded authentication
- Desktop uses a two-panel publisher story + secure form layout.
- Mobile uses a single-column layout with the sign-in form first.
- Publisher identity uses Primary/Dark Logo when configured, Square Mark + name as fallback, then generated monogram + name.
- Full wordmark logos are not visually duplicated with redundant publisher-name text.
- Auth pages suppress the normal public header/footer so the auth shell owns the full viewport.
- Existing authentication form markup is embedded unchanged inside the branded shell.

## Product copy
- Hero: “Your newspaper. Your archive. Your reading library — all in one place.”
- Includes Today’s ePaper, Searchable Archive, and Clipping & Sharing Tools descriptions.
- Twelve short quotes are stored in the server-side pool and one is selected per auth render; when an auth session is active, the immediately previous quote is avoided.

## Theme behavior
- Split Editorial: dark premium editorial treatment.
- Gradient Focus: dark accent-led treatment.
- Minimal Access: light branded split treatment.
- New unset/default auth-theme fallback is Split Editorial; any explicitly stored tenant theme remains authoritative.

## Control-plane boundary
- No Platform host URL is introduced in the login UI.
- Existing same-domain `/login`, `/subscribe`, and `/account/subscription` behavior is preserved.
- Digiti Platform remains background-only for identity/access verification.

## Regression preservation
- v1.1.4.1 Source Page QR protected hashes: PASS.
- v1.1.5.1 Settings normalization: PASS.
- v1.1.6.1 header branding: PASS.
- v1.1.6.2 Admin CRUD drawer: PASS.
- v1.1.7 Guest Clipping: PASS.
- v1.1.8 Reader prefetch + same-domain subscription: PASS.

## Browser checks
- 1440×900 branded split layout: PASS.
- 390×844 form-first mobile layout: PASS.
- Publisher logo alt text: PASS.
- Three product value features: PASS.
- Quote slot: PASS.
- Existing auth form remains embedded: PASS.
- Minimal Access light-theme treatment: PASS.

## Live verification required
After deployment, verify the real `/login` form still authenticates successfully with email/password/MFA as configured, confirm the configured publisher logo/dark logo is displayed correctly, refresh several times to observe quote rotation, and confirm the browser never leaves the current ePaper domain during authentication/subscription navigation.
