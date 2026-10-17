# ePaper × Digiti Platform — bounded authorization cache and lifecycle hardening

Status: Draft PR #23 (unmerged, no Cloudways deployment). October 17, 2026.

## Changes

- `codebase/app/Services/PlatformAccess.php`: product ePaper access is derived from the authenticated Platform `/internal/v1/tenants/{guid}/access` response. Unknown states, malformed responses or `ok:false` never grant FULL. Redis uses new `access:v2:{tenant}` keys, a configurable 10-second default TTL (1–30 seconds), and a PHP-local memo capped by TTL to avoid repeated HTTP calls during one request. When the Platform is unreachable, return UNAVAILABLE (fail closed, HTTP 503 for the data-plane guard).
- `codebase/app/Services/Entitlements.php`: an ePaper cached entitlement cannot bypass current access; `all`, `has`, and `limit` deny when access is not FULL (including errors).
- `codebase/app/Services/DomainResolver.php`: only new `domain:v2:` and `tenant-meta:v2:` entries are used, default TTL 15 seconds with hard maximum 30 seconds. Domain product key, status and tenant metadata are validated on activation. Automatic fallback from failed domain resolution to the canonical default tenant is removed: this would otherwise permit disabled domains to regain a mapping.
- `platform/app/Domain/Routing/DomainLifecyclePolicy.php`: Admin domain Edit cannot reactivate a suspended/removed domain or remove a domain without clearing its primary bit. Explicit Verify/Activate/Remove paths must be used. Verify and Activate are guarded against stale removed-domain transitions; removal is performed within an Infrastructure transaction and invalidates Platform's Redis mapping.
- Both application regression suites now exercise/access-check the new policies. Database fixture covers removal.

## Security/availability implications

These changes intentionally trade some control-plane traffic for tighter permission freshness. For most public pages, ePaper now fetches fresh host authority no less frequently than every 30 seconds, and it revalidates service access no less frequently than every 30 seconds (10-second default). On an outage, stale Redis FULL access is **not** used after the short TTL, so affected requests may receive HTTP 503 until Platform recovers. Configure alerts and recovery procedures before deployment.

### Not instant revocation

This is **bounded origin-side cache staleness**, not an end-to-end instant revocation system. CDNs, browsers and service workers may retain publicly cacheable HTML/assets longer; signed media URLs may survive until token expiry; existing PHP sessions and custom route flows require separate acceptance. Do not claim that tenant suspension instantly purges all caches.

A future signed invalidation system should publish tenant/domain mutation events on Platform commit, deliver to ePaper with auth + replay protection, invalidate Redis/file caches and CDN keys, and invalidate affected user sessions or access leases. Redis outages require fallback or retry/ack rather than silent message loss. The new cache namespaces stop relying on historical 180/600/900-second keys upon deployment, without deleting users' production data.

## Staging gates

1. Verify active, suspended, expired, removed, wrong product and unregistered hostname on two isolated tenants using the real Platform token and MariaDB.
2. Simulate Platform, DNS, Redis and network outages; ensure no cached FULL grant persists beyond the configured access TTL. Confirm response codes, retry behavior and request volume.
3. Benchmark Reader/Home TTFB and Platform calls at 1, 10, 100 req/sec under warm and cold caches, with PHP-FPM workers and realistic editions; no performance percentages are claimed by the source tests.
4. Explicitly test Cloudflare/browser/service-worker caching of public pages and signed media after tenant suspension.
5. Review existing Cloudways canonical-default-tenant installations: implicit fallback was removed. Register canonical domains properly in Platform and test the migration before promotion.
6. Complete Platform secret rotation and history remediation, private-config cutover, isolated Platform restore, DigiOps atomic deploy and rollback. The prior ePaper backup does not validate Platform recovery.

**Never deploy draft PR #23 by direct production git pull:** the PR also untracks the previous live-looking `platform/config/config.php` and installer lock. Deleting those tracked files can remove working runtime configuration.
