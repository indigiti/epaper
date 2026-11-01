# Canonical ePaper Hostname Recovery

Target branch: `cw-platform` only. This is not a general delete/re-create or product bypass.

## Problem

An archived canonical hostname, such as `epaper.digiti.in`, retains its unique `normalized_hostname`, so Add Domain cannot insert a second copy. Previously, the generic domain Restore path allowed only customer domain types and explicitly rejected the application's canonical hostname. The UX therefore trapped administrators between a duplicate-domain warning and a blocked Restore flow.

## Resolution

For a canonical EPAPER hostname with a matching registered application's `base_url`, a Platform `SUPER_ADMIN` may review and restore the *existing* archived domain record, **without creating another product/application/tenant/domain**. The change requires typing the exact hostname and checking two explicit confirmations. The original foreign keys and domain GUID are retained; status becomes `AWAITING_DNS` and DNS/TLS/go-live proof is cleared.

The recovery is denied if the underlying product, application, organisation or tenant is not `ACTIVE`, if their GUID relationships disagree, if the hostname is not genuinely canonical for an EPAPER application, or if its type is PLATFORM/PLATFORM_AUTH or unrelated infrastructure. An archived product must be restored and subsequently activated first. This is deliberate: bypassing that dependency would create a domain whose owner product is unavailable.

After recovery, the administrator is directed to Publisher Onboarding for routing checks; the main ePaper install and its editions are **not** reinstalled or migrated. Domain and reader caches must be checked separately.

## How to use after deploying reviewed cw-platform build

1. Make verified backups of Platform and ePaper and, preferably, test on an isolated Cloudways staging clone first.
2. Open Domain Registry → `epaper.digiti.in` → **Review & restore**.
3. If a prerequisite is inactive, open its registered record and restore/activate it first. Do not create a replacement product.
4. Confirm the canonical hostname and extra warning. Recovery is *not* immediate go-live.
5. Check `/admin/onboarding?domain=<existing-guid>`, DNS, Cloudways domain alias/TLS, correct owner tenant, and `https://epaper.digiti.in/health/platform`.
6. Do not reassign the canonical host to DCN. DCN must use its own customer hostname and tenant.

**Limitations:** GitHub CI runs synthetic MySQL tests, not a real production database or Cloudways TLS check. No live data is modified by code deployment alone.

## If you do not want to restore the previous product or linked records

On the canonical hostname Restore screen, expand **“I do not want to restore the old product or other records.”** This displays the number of registered applications, publisher tenants and domains belonging to the archived product, explains the database's unique EPAPER product key / hostname constraints, and offers a **Do not restore — leave existing records archived** navigation choice. Opening this preview or selecting the leave-archived link makes **no database changes**.

A genuinely fresh setup needs a **new isolated staging Cloudways application and empty database**, private configuration, independent media and Redis, and a distinct staging hostname. Do not attempt to bypass archived product references on the existing production Platform by creating a second EPAPER product or reusing the same canonical domain. `/admin/staging-reset`, when enabled on isolated staging, only restarts test-domain onboarding—it is **not** a complete database reset. A production cutover would require a separate backed-up migration and acceptance plan.
