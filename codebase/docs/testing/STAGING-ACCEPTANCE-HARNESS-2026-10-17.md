# EP-DI staging authentication, Platform/Redis outage and browser-cache acceptance
Prepared 2026-10-17. Applies to PR #1 only. No live user data or secrets are stored in this document.

## Automated isolated simulations (no Cloudways access)
From codebase run: php bin/release-check.php and node tests/browser-cache-isolation.cjs.
- tests/app-platform-outage-integration.php calls the real App::serviceAccess method with fake isolated Platform/Redis clients, covering real exception behavior, FULL, RESTRICTED, BLOCKED, malformed responses and cache behavior.
- tests/redis-outage-concurrency.php launches ten concurrent PHP processes sharing a private file-lock bucket while Redis is unavailable. The expected limit is 3 allowed / 7 denied.
- tests/browser-cache-isolation.cjs executes actual service-worker.js in a Node VM with fake CacheStorage and fetch, testing old cached pages, A -> B -> guest sessions, offline fallback, shell asset caching and protected offline bytes.
- These are simulations, NOT proof that production Redis/Platform, Cloudways, Varnish or a physical browser behave correctly.

## Authenticated, read-only HTTP staging acceptance
Requires deployed security PR code on an independently controlled NON-PRODUCTION staging host, TLS and staged fake users.
Set these environment variables in a private operator shell (never commit them or paste cookies in issue comments):

    EPDI_STAGE_BASE_URL=https://your-staging-host
    EPDI_STAGE_APPROVED_HOST=your-staging-host
    EPDI_STAGE_READER_A_COOKIE_FILE=/secure/staging/reader-a.cookies
    EPDI_STAGE_READER_B_COOKIE_FILE=/secure/staging/reader-b.cookies
    EPDI_STAGE_READER_A_MARKER=VisibleUniqueFixtureMarkerA
    EPDI_STAGE_READER_B_MARKER=VisibleUniqueFixtureMarkerB
    EPDI_STAGE_ROLE_OWNER_COOKIE_FILE=/secure/staging/owner.cookies
    EPDI_STAGE_ROLE_ADMIN_COOKIE_FILE=/secure/staging/admin.cookies
    EPDI_STAGE_ROLE_PUBLISHER_ADMIN_COOKIE_FILE=/secure/staging/publisher-admin.cookies
    EPDI_STAGE_ROLE_EDITOR_COOKIE_FILE=/secure/staging/editor.cookies
    EPDI_STAGE_ROLE_OPERATOR_COOKIE_FILE=/secure/staging/operator.cookies

    php bin/staging-auth-audit.php

Use Netscape-format cookie files from intentionally created staging-only sessions, mode 0600. The script uses GET only, refuses epaper.digiti.in, requires an exact approved HTTPS hostname, disables redirects, checks that readers are authenticated and that home/archive/search are no-store, compares visible fixture markers, and tests the five role permission routes. It prints no response HTML, cookies or user identifiers. Missing fixture markers or roles results in INCOMPLETE (exit 2), not PASS.

## Actual Chromium browser isolation (operator-run only)
On an isolated staging workstation with Playwright and Chromium installed, export two private Playwright storageState JSONs (mode 0600) from two test readers. Set EPDI_STAGE_READER_A_STORAGE_STATE and EPDI_STAGE_READER_B_STORAGE_STATE to their private paths; keep the staging URL, approved host and fixture marker variables above. Run:

    node bin/staging-browser-isolation.cjs

One real Chromium context is retained while cookies are swapped from reader A to B, then cleared to guest. Service worker/CacheStorage remain intact. Verify response Cache-Control, absence of cached HTML, correct A/B markers and no guest leakage. Do not run with production readers. This browser check is NOT run in public GitHub Actions.

## Controlled fault injections: staging isolated dependencies only
1. Point the staging app to a dedicated mock Platform service. Verify FULL and RESTRICTED read success, BLOCKED denial, malformed response denial and HTTP 503/no-store after Platform becomes unavailable and the valid 180-second access cache expires.
2. Disable only the test deployment Redis instance. Hit non-production login/OTP endpoints across independent PHP sessions and processes and verify rate limiting continues and returns 429 after the configured limit; inspect fallback directory 0700 and counter files 0600.
3. Test an unwritable limiter directory on a disposable instance: requests must be denied (never open the limit). Restore the directory before accepting staging.
4. Confirm all PHP-FPM nodes share the fallback directory or use Redis for real distributed limiting. File locks are local to shared filesystem, not separate servers.
5. Inspect CDN/Varnish/cache Age and Cache-Control headers for reader A then reader B; ensure no private HTML is cached. Exercise logout and the encrypted Offline Vault on real mobile/desktop browsers.
6. Restore Platform/Redis test services, check queue workers and scheduler, and retain an operator-approved test report.

## Gate status and order
GitHub CI can validate PHP/Node simulations. Authenticated HTTP and actual Chromium tests require staged test sessions and host access; they are UNVERIFIED until run with recorded evidence. The Cloudways backup + isolated test restore remains the LAST PREPARATION GATE. Any live credential rotation, encryption-key cutover, Git-history rewrite, merge or production deployment may occur ONLY AFTER that verified recovery gate, not before.