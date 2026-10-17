# ePaper SaaS v1.1.8 — Final Re-audit

Release state: **TESTED**

## Scope
v1.1.8 improves Reader page-transition readiness and closes the user-facing control-plane URL leak in the clipping subscription conversion flow.

## Reader performance
- Tenant-scoped Redis state cache: `reader:state:v1:{tenant}:{slug-hash}:{page}`.
- Cache TTL: 300 seconds.
- Redis stores only page/asset metadata and signed URLs; it does not store WebP page binaries.
- N+1 and N-1 PREVIEW images are fetched and decoded ahead of navigation.
- N+2/N-2 state is warmed in the background; N+2 preview is skipped under Low Data conditions.
- Swipe direction immediately raises the likely next/previous page to prefetch priority.
- High Quality may warm adjacent HIGHRES after PREVIEW.
- Service Worker media fallback is bounded to 18 objects and remains network/HTTP-cache first.

## Subscription/control-plane boundary
- Reader CTA uses `/subscribe` on the current ePaper domain.
- Guest `/subscribe` keeps the user on the tenant domain and sends sign-in through the local `/login` route.
- Authenticated readers use `/account/subscription`.
- The ePaper backend reads access and entitlements server-to-server.
- No `https://platform.digiti.in` user-facing fallback remains in application/public assets.

## Regression preservation
- v1.1.4.1 Source Page QR exact protected hashes: PASS.
- v1.1.5.1 Settings normalization guards: PASS.
- v1.1.6 Smart Social Sharing guards: PASS.
- v1.1.6.1 header dedup guards: PASS.
- v1.1.6.2 Account/Admin CRUD guards: PASS.
- v1.1.7 Guest Clipping guards: PASS.

## Browser checks
Predictive Reader prefetch test:
- next state prefetch PASS
- previous state prefetch PASS
- second-forward state warming PASS
- adjacent preview decode PASS
- new window scheduled after page change PASS
- swipe direction prediction PASS
- JavaScript errors 0

Guest clipping conversion test:
- guest quota gate PASS
- subscription entitlement gate PASS
- same-domain `/subscribe` CTA PASS

## Live verification required
After deployment, verify Redis is enabled/connected, inspect `X-Reader-State-Cache` on repeated Reader state requests for MISS → HIT, and test repeated forward/back swipes on real PREVIEW WebP media. Confirm `/subscribe` and `/account/subscription` never navigate to the control-plane host.
