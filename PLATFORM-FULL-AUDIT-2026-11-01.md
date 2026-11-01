# Digiti Platform — Whole-codebase pre/post corrective audit
**Audit date:** 2026-11-01
**Scope:** GitHub \`indigiti/epaper\`, Platform deployment branch only (\`cw-platform\`).
**Before:** \`31a7fe9a0f85c72cf21aebea5472e37716160be8\`
**Fix branch:** \`fix/platform-canonical-registration-20261101\`
**Production deploy:** NOT performed. No production database mutations, ePaper media modifications or DNS changes.

## Audit coverage and method

Reviewed the full repository tree, PHP application/router modules in \`app/\`, Core security/session/API/Billing services, Admin CRUD/onboarding/release/restore flows, domain/edge services, \`public_html\` installer/updater/entrypoints, \`bin/\` worker and scheduler, \`database/schema.sql\`, UI controls, and existing test/CI gates. Followed cross-module paths for product → application → organisation → tenant → domain and financial-history dependencies. Added source-level security assertions and isolated MySQL 8 integration acceptance tests. Full PHP lint covers \`app/\`, \`public_html/\`, \`bin/\` and \`tests/\`.

This is a broad code-level audit with targeted integration tests. It does NOT imply a full authenticated Cloudways GUI, browser, live payment-provider or ePaper reader acceptance test.

## Pre-audit findings and corrections

| Severity | Pre-audit issue | Code correction | Verification |
|---|---|---|---|
| P0 | Canonical ePaper application hostname rejected by Add Domain even after release | Single CanonicalDomainRegistration classifier; active matching EPAPER app + SUPER_ADMIN becomes SAAS_DEFAULT; aliases CUSTOMER_CUSTOM | Pure positive/negative unit contracts + MySQL retained-product recreation |
| P0 | Publisher Onboarding and Domain Registry disagreed on canonical-host rules | Both use same classifier; onboarding selects compatible app and requires explicit canonical acknowledgement; customer aliases still supported | Source checks, onboarding contracts |
| P0 | Direct auth for one publisher could include membership in another or succeed without membership | Require active membership for selected organisation; return only chosen publisher membership | Security contracts |
| P0 | Product-scoped service account could query other product tenant/domain APIs, including cached resolutions | Central product/tenant/organisation authorization; apply to domain DB/cache, tenant, access, entitlements, memberships, heartbeat, external audit/error, metering | Security contracts |
| P0 | Archived domains could be picked up and status-changed by background DNS worker | Eligible-status whitelist + conditional update prevents REMOVED/other transitions | Worker source contracts |
| P0 | Admin DNS verification endpoints could also change archived domains or auto-promote to ACTIVE | Active parent/foreign-key checks, allowed-status guards, compare-and-update, and explicit go-live-only activation across both entry points | Source and lifecycle contracts |
| P0 | Background worker could set a domain ACTIVE without operator go-live approval | Never auto-promote to ACTIVE; failed DNS remains failed pending operator intervention | Worker source contracts |
| P0 | Verified payment webhooks/outbox entries were marked processed despite no posting handler | Unsupported work fails visibly and remains auditable; no fake PROCESSED/settled state | Worker source contracts |
| P1 | OAuth authorization code exchange was susceptible to concurrent replay and returned unrelated memberships | Atomic single-use code claim, active app/product/user, S256 challenge, active memberships scoped to application product | Source + real MySQL successful/replay/inactive tests |
| P1 | MySQL fallback login rate limiter read a count before increment and could race | Atomic SQL upsert with fail-closed handling when Redis unavailable | Source + MySQL sequential limit tests |
| P1 | Support impersonation granted to any admin; grant consumption non-atomic | SUPER_ADMIN only, active identities/org/product binding, conditional one-time consumption | Security contracts |
| P1 | Installer could re-run if install.lock disappeared and reactivate/resurrect EPAPER records | Refuse existing config and any existing Platform DB tables; require explicit new empty DB confirmation; delay config write until initialized | Installer contracts |
| P1 | Server error-event logging SQL placeholder count incorrect | Correct insert placeholders so exception records persist | Source contract |
| P1 | Webhook deduplication used event name for Razorpay rather than unique event ID | Deduplicate Razorpay by authenticated body digest (event-ID header is not signed); Stripe by signed event ID | Security contract + provider docs |
| P1 | Verified webhook record and outbox insertion not atomic | Transactionally persist both; sanitize stored headers; reject invalid signatures/oversized payloads | Security contract |
| P1 | Stripe webhook signature had no freshness check | Reject signed timestamps outside ±300 seconds | Signature source contract |
| P1 | Razorpay webhook verification used API key secret rather than a dedicated webhook signing secret | Require billing.razorpay_webhook_secret (fail closed if unset); verify raw body HMAC | Webhook source contract |
| P1 | Inactive/archived tenant could still report FULL access or entitlements | Check tenant, organisation, product status; return BLOCKED and empty entitlements | Security contract |
| P1 | Usage meter could accept arbitrary products/features, negative/non-finite deltas, arbitrary period, and race on insert | Validate tenant/feature/period/delta; atomic upsert on existing unique index; invalidate entitlement cache | Security contracts |
| P1 | Creating invoice used float math with unrestricted/negative values, unchecked tenant | Strict decimal input, integer-minor conversion, active tenant and amount cap | Source contract |
| P1 | Manual subscription creation could duplicate active access and was not clearly labeled manual | Active parent checks, duplicate subscription refusal, manual-access acknowledgement | Source contract |
| P1 | Scheduler generated duplicates and unsupported jobs, no lock without Redis | MySQL advisory lock, bound queue, expired lock recovery, auth cleanup direct | Scheduler contracts |
| P2 | Production installer could leak SQL exception information | Redact PDO errors from web response | Installer contract |
| P2 | Onboarding allowed selected tenant from another EPAPER product | Verify the product GUID against selected application's product | Onboarding source contract |
| Existing | Archived registry resources indefinitely reserved names | Earlier release workflow retained original GUID/financial references while releasing product/application/domain keys; unaffected by new patch | Existing financial lineage MySQL tests |

## Remaining high-value items — **NOT solved in this PR**

1. **Payment provider reconciliation and notification/outbox delivery:** Adapters are not complete processors. The worker now fails visibly instead of acknowledging work falsely; implement an authenticated idempotent gateway-specific transaction/state machine with refund reconciliation before enabling automated financial posting.
2. **Usage idempotency:** Atomic increment prevents lost concurrent updates but a retried request can count twice. Introduce a durable unique usage-event journal with a production-safe schema migration and idempotency keys before relying on it for billable usage.
3. **Domain routing availability gate:** \`resolveDomain()\` currently permits \`ROUTING_VALID\` and \`SSL_PROVISIONING\` as well as ACTIVE for legacy compatibility. Enforcing ACTIVE-only may break current publishers. Require a measured staged rollout and explicit source-of-truth migration.
4. **ePaper domain cache:** Platform Redis invalidation does NOT immediately purge the separate ePaper Redis/file-based resolver caches. Validate TTL and targeted cache purge for migration/reassignment.
5. **Production migration and browser acceptance:** Cannot prove Cloudways app aliases, TLS, deployment branch, actual product/tenant GUIDs, media isolation, roles, OAuth callbacks, reader editions or responsive browser UX from GitHub alone.
6. **Financial retention, tax and compliance:** Current invoice form is simple. Production-grade invoicing, GST, credit notes, tax, gateway settlements, refunds, reports and data-retention policy require a separate review before commercial go-live.
7. **Administrator role/least privilege:** Most admin screens require platform administrator rather than fine-grained platform permission checks. Several operations now explicitly enforce SUPER_ADMIN, but a full RBAC migration and audit is still needed.
8. **Historical data and cleanups:** Resource Release physically deletes archived operational records while retaining financial GUIDs; perform full backup and **isolated production-data snapshot acceptance** before any irreversible live release, including external ePaper media cleanup.
9. **Installer reliability:** New-database checks prevent corrupting existing installs. A partial first install on failure requires creating a fresh database or manual recovery; no automatic rollback of MySQL DDL is claimed.

## Regression and deployment gates

- PHP syntax lint: \`find app public_html bin tests -name '*.php' | xargs php -l\`
- Existing onboarding source, operator navigation, registry actions, and cross-module audit checks.
- New platform security contracts: canonical types, worker safety, OAuth single-use, service-product scope, billing/admin controls, webhook event IDs, Stripe freshness, installer isolation.
- Isolated MySQL 8 integration: archived EPAPER/AMG registry release with retained invoice/payment/refund/audit GUIDs; recreate original EPAPER application/product/domain keys; classify canonical and customer hostnames; OAuth replay and inactive-app checks.
- Final \`cw-platform\` push CI after promotion; staging snapshot and real authenticated UI/Cloudways acceptance remain required.

## Specific acceptance flow for \`epaper.digiti.in\`

1. Back up Platform and ePaper, including the original domain/tenant map, database and media. No reset or new installation is required.
2. Confirm the old hostname no longer exists in \`domains\` (if archived, choose Restore or Release, not duplicate insertion). Confirm the current EPAPER product/application, AMG organisation, and intended AMG tenant are ACTIVE and compatible.
3. **Domains → Add Domain**: choose AMG's tenant and the EPAPER application with Base URL \`https://epaper.digiti.in\`. Enter \`epaper.digiti.in\`. A Platform SUPER_ADMIN registers it as type \`SAAS_DEFAULT\`, not \`CUSTOMER_CUSTOM\`.
4. Alternatively, Publisher Onboarding can select that same application and use the main-hostname acknowledgement.
5. Confirm domain record has original/new intended tenant GUID, correct product/app GUIDs, \`AWAITING_DNS\` initial state, and a unique hostname. The main ePaper host remains AMG, not DCN.
6. Verify DNS/TLS/Cloudways routing, publisher login and correct editions, then complete explicit go-live approval. Separately confirm \`epaper.dcnnews.co.in\` points to DCN/DCNEP and not AMG.
7. Confirm ePaper domain cache has expired or was explicitly purged before concluding the mapping is fixed.

**Deployment scope:** Only \`cw-platform\`; \`main\` and \`cw-epaper\` must remain unchanged. No automatic release, deletion, migration or Cloudways deployment is triggered by this PR.

## Additional deployment requirement for payment webhooks

The existing production `config/config.php` is private and is not modified by GitHub deployment. If Razorpay webhooks are enabled, separately provision the dedicated `billing.razorpay_webhook_secret` from the Razorpay Webhooks configuration before testing. Do not reuse the Razorpay API key secret. The provider-adapter payout/reconciliation handler remains incomplete and will fail visibly until implemented; this patch must not be construed as enabling automated payment posting.
