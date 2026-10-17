# Digiti Platform multilayer foundation — 17 October 2026

## Scope

This change creates the first vertical slice of a multilayer control-plane application. The existing Platform `App.php`, Admin `Crud.php`, billing and domain modules are not yet fully decomposed.

| Layer | Source | Responsibility |
|---|---|---|
| Presentation | `app/App.php`, `public_html/` | HTTP routes, machine authentication, JSON responses and Admin UI |
| Application | `app/Application/Identity/DirectLogin.php` | Direct publisher authentication use case, audits and throttling |
| Domain | `app/Domain/Identity/TenantBoundaryPolicy.php` | Pure active-product, organisation, tenant and service-binding invariants |
| Infrastructure | `app/Infrastructure/Identity/IdentityRepository.php` | Database queries scoped to application and organisation |
| Supporting technical services | `app/Core/`, `app/Billing/` | Legacy services during incremental migration |

### Preserved wire contracts

`POST /internal/v1/auth/direct` still requires `auth.direct` scope, accepts the existing `client_id`, `product_tenant_guid`, `email`, `password`, `totp` fields, and responds with `{ok:true, identity:{user,memberships,authenticated_at,assurance,product_tenant_guid,application_guid}}` on success. No password hash is returned.

**New security conditions:** Machine credentials must be bound to the requested product; user must have an ACTIVE membership in the requested tenant's organisation. Only that organisation's memberships are returned. Existing accounts that lack membership need to be provisioned before an actual release. The OAuth code exchange uses a one-row conditional UPDATE to prevent concurrent replay. This remains subject to a MariaDB concurrency test.

### Exposure / deployment blockers

Previous public commits included `platform/config/config.php` and `platform/storage/install.lock`. This draft branch removes these files from its proposed tree and adds `.gitignore`, but **does not revoke the disclosed secrets or scrub historical commits**. Follow security issue #2 and platform issue #22. Do not push credentials, passwords, backup archives or unredacted logs to GitHub.

**Never pull this draft branch onto a live Cloudways application.** Git can delete the previously tracked runtime configuration. An operator must first arrange protected runtime config outside replaceable releases, test PHP-FPM / CLI / cron / Supervisor access, take a separate Platform database+app backup and verify isolated restore. ePaper's prior backup is not evidence that the Platform data is recoverable.

Platform and ePaper require separate application roots, MariaDB databases and deploy artifacts. Their outer `public_html` trees cannot be flattened together. See DigiOps blocker #21.

The old `SHA256SUMS` file and previously released manifest documents remain historical. Regenerate checksums from an approved source-only artifact before promotion.

### Automated tests

`php platform/tests/core-smoke.php`
`php platform/tests/multilayer-policy.php`
`php platform/tests/layer-contract.php`
`php platform/tests/build-guard.php`
`php platform/tests/admin-crud-guard.php`

The dedicated root GitHub Actions workflow also performs PHP syntax checking. These source checks are not a substitute for real MariaDB, Redis, Cloudways and DigiOps staging acceptance.

### Next layer slices

1. Move domain resolution, hostname cache invalidation and entitlements into application/domain/infrastructure components with tenant-bound negative and positive cache tests.
2. Extract billing/payment/webhook idempotency and transactional outbox with race tests.
3. Split Admin CRUD routes into one controller per module; add field-level authorization.
4. Build contract tests against ePaper's PlatformClient and authenticated browser tests across multiple product tenants.
5. Complete private config, security key rotation, all-ref history remediation and dedicated deploy packaging before production.

### Second slice: database-authoritative domain routing

`App::resolveDomain` delegates to `Application/Routing/ResolveDomain`, `Domain/Routing/DomainResolutionPolicy`, and `Infrastructure/Routing/DomainRepository`. Domain mapping is checked directly in MariaDB for each **control-plane** resolution rather than trusting cached Platform domain entries. This prevents the control-plane API from returning a cached mapping after domain or parent status changes. The policy verifies matching organisation/product relationships and product-bound service-account scope. Historical `ok:true,cached:true` responses are no longer produced by this Platform endpoint; successful responses carry `cached:false`.

**Important operational limit:** ePaper's own `DomainResolver` caches mappings for its configured TTL (600 seconds by default); this change alone cannot guarantee instant tenant/domain revocation at the product edge. Add a signed/validated cache invalidation or bounded-staleness fail-closed policy as a separate integration gate.

### Redis outage limiter improvement

`Core/RateLimiter` now uses a single MariaDB atomic upsert for counters, replacing a read-before-write race. A concurrent SELECT may conservatively deny requests early but should not grant excess requests based on stale counts. Validate with real parallel MariaDB/PHP workers on staging; no such stress acceptance is implied by the source CI checks.
