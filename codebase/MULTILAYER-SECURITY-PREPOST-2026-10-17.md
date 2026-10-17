# EP-DI ePaper — multi-layer security pre/post audit
**Date:** 17 October 2026  
**Baseline:** `24eda9b041af97bcd1620e408d3fa523c9a26723`  
**Post-patch:** draft PR #1, subsequent commits through this report  
**Scope:** Application data-plane, frontend page caching, Redis outage handling, background maintenance and regression CI.

## Before vs after

| Risk | Before | After (source-level) | Status |
|---|---|---|---|
| Platform / publisher access outage | `App::serviceAccess()` catches any exception and returns `FULL` | Fail-closed `ServiceAccessPolicy`, `UNAVAILABLE` yields HTTP 503 with no-store and Retry-After; only FULL/RESTRICTED can read | Patched, static/unit regression |
| Absent/invalid Platform state | Missing cache state defaulted to `FULL`, missing fresh state defaulted to `BLOCKED` | Explicit syntactic validation and rejection; missing/invalid state is `UNAVAILABLE` | Patched |
| Logged-in cache headers | Selected public routes send public cache headers despite potentially personalized navigation | Both `Http::publicCache` and `Http::publicStaticCache` force private/no-store if session identity exists | Patched, static guard |
| PWA session-bound HTML | Successful dynamic HTML navigation persisted in shared CacheStorage under `PAGE_CACHE` | Dynamic navigations are network-only; previous page cache cleared on SW activation; encrypted Offline Vault still separate | Patched, static guard |
| Redis outage / throttling | Per-PHP-session fallback lets fresh sessions evade a shared limit | Atomic Redis Lua increment/expiry; private, file-locked counters across sessions when Redis is unavailable; deny on filesystem failure | Patched, local CLI regression |
| Rate limit filesystem growth | No shared fallback storage to maintain | Bounded cron cleanup with expiry grace | Patched |

## Regression coverage

- `tests/service-access-failclosed.php`: allowed/blocked/invalid Platform states, 503 and no-store path.
- `tests/private-html-cache-security.php`: public/private cache header selection and service-worker dynamic-navigation isolation; retain legitimate static offline shell caching.
- `tests/rate-limiter-fallback.php`: file counters enforce limits after PHP-session reset, file permissions, expired counter pruning.
- The three tests are invoked by `bin/release-check.php` and root `.github/workflows/epaper-ci.yml`.
- Existing SQL-tenant isolation, Reader parity, clip, queue and production readiness static checks remain part of that release suite.

## Mandatory staging acceptance — NOT VERIFIED

1. With stubbed/unavailable Platform tenant-access endpoint and **no prior cache**, confirm new reader requests receive 503, not authorized FULL access. Confirm known BLOCKED remains 402 and FULL/RESTRICTED continue.
2. With logged-in reader A, open home, archive, article and newspaper page; inspect HTTP headers and browser CacheStorage. Sign out, log in as reader B and ensure reader A's history/identity never reappears from service worker. Offline Vault remains functional for the right user.
3. Simulate Redis outage and send many cross-session login/OTP/API requests on **each PHP-FPM instance**, verifying filesystem counters block excess attempts. Verify the rate-limits directory is private/writable and maintenance cleanup runs.
4. Confirm single shared storage or Redis availability if the runtime spans multiple hosts. Local file fallback is not a distributed lock across different machines.
5. Verify response cache at Cloudways/Varnish/CDN respects `Cache-Control: private, no-store`, and that old service-worker page cache is purged after deployment.
6. Confirm acceptable UX/monitoring for legitimate readers during Platform outage. Consider tenant-specific signed grace policy only with independent security review.
7. Verify scheduler and worker health, end-to-end PDF/OCR publishing, clipping, reader downloads/offline and physical browser QA.

## Deferred operator gates

No code commit is evidence of rotation or backup. Keep PR #1 draft until staging acceptance and the security incident tracker are resolved. By user request, Cloudways backup + **isolated test restore** may be the last preparation gate, but must precede any live credential rotation, application-key migration, history rewrite, merge or deployment.

**Final verdict:** Security source changes implemented. Runtime/Cloudways verification is not complete; this is **not a production-ready release**.
