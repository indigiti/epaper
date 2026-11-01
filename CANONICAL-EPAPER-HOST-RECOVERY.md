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

The canonical hostname screen now has a **Do not restore — release archived resources** option with links to product/hostname/organisation cleanup reviews.

- **Release archived hostname:** physically deletes the archived Platform domain row, freeing its unique hostname. Original GUID and hostname are written to an immutable registry.release audit event.
- **Release archived EPAPER product:** in one transaction, removes archived domains, application registrations, OAuth redirects, auth codes, heartbeats, service credentials, unused plans/features and unreferenced tenants. Frees the old EPAPER product key and application/feature keys.
- **Release archived organisation code:** frees an archived organisation code, preserving original GUID, legal name and historical records if still referenced.
- **Financial records preserved:** original invoices, invoice items, payments, payment attempts, refunds, subscriptions, usage meters and audit logs remain. Minimal historical identity rows (product, tenant, plan, feature) remain only when foreign keys require them; unique retired keys no longer block reuse.
- **Permission/preconditions:** Platform SUPER_ADMIN, backup acknowledgement, typed identifier, irreversible action confirmation, no active subscriptions/pending payments/refunds and no unarchived dependent operational records. Dependency preview checks block unsupported deletion.
- **Media/cache limitation:** Platform invalidates its Redis hostname cache. ePaper has independent Redis/file caches needing TTL or a targeted purge, and remote PDFs/images are not deleted by Platform. A separate ePaper reference and backup audit is needed to reclaim its media files.
- **Cutover:** freeing a hostname is not go-live. Recreate valid product/application/tenant/domain mapping, complete onboarding, verify DNS/SSL and confirm isolated newspaper data.

No database cleanup runs on deployment. Every release is an explicit irreversible administrator action, and must first be tested on an isolated Cloudways snapshot.
