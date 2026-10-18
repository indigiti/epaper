# Existing Cloudways staging acceptance — no new server

These checks use the same existing Platform and ePaper Cloudways installations.
No provisioning, schema migration or destructive mutations are performed.

Run from a computer or Cloudways SSH terminal that resolves the staging URLs:

    python3 tests/staging_acceptance.py --only platform
    python3 tests/staging_acceptance.py --only epaper

The default URLs are https://platform.digiti.in and https://epaper.digiti.in.
Use --platform and --epaper for a different existing staging hostname.

First pass covers HTTP liveness, unauthenticated Platform redirects,
GET /login CSRF token, rejected forged CSRF, service auth denial, and
public ePaper routes. Authenticated passes additionally need environment
variables STAGING_PLATFORM_EMAIL and STAGING_PLATFORM_PASSWORD; optionally
STAGING_PLATFORM_TOTP. Never commit credentials or send them in chat.

Optional read-only fixtures:
- STAGING_SERVICE_TOKEN (requires tenant.read/access.read/entitlements.read)
  paired with STAGING_TENANT_GUID; checks all three tenant service endpoints.
- STAGING_EXPECT_ACCESS_STATE, e.g. BLOCKED, compares the access snapshot
  after a separately authorized staging-only suspension.
- STAGING_EDITION_SLUG, STAGING_PUBLIC_CLIP_SLUG for public Reader/clip checks.

Before running mutations manually, use a clearly labelled disposable staging
tenant, edition, PDF and service account. Confirm Platform login/roles/CSRF
first, then domain verify/status management, ePaper upload/publish/Reader,
clipping, AI editorial review, page reordering and queue recovery, and finally
Platform-to-ePaper suspension/cache invalidation. Record request GUID,
test tenant, before/after snapshots and state restoration. No actual domain
DNS records or live subscriber resources may be modified by this smoke helper.

Repeat functional smoke after each refactor change. After all acceptance,
security, performance and Cloudways restore gates pass, obtain approval and
merge/deploy Platform first, then ePaper, rerunning this exact script.
