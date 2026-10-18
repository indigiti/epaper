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

## Cloudways Git deployment detail

The acceptance script is now **also included in `cw-epaper`**. When
Deployment via Git pulls `cw-epaper` into the application checkout, run it
from the Git project root (the directory containing `tests/`):

    python3 tests/staging_acceptance.py --only epaper
    python3 tests/staging_acceptance.py --only platform

If it is not there after pulling, verify the actual deployed branch and
working directory. These HTTP checks need outbound HTTPS to the two staging
domains. AI tests already on `cw-epaper` can be run separately, for example
`php tests/ai-review-static.php`; the MariaDB AI test creates test databases
and must be run **only against an explicitly disposable database**, not the
staging application database.

This commit copies only testing/documentation/CI files; it does not include
the unmerged multilayer changes from PR #31.
