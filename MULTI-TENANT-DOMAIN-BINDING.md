# Shared ePaper Application — Multi-tenant Host Binding

Production: `epaper.digiti.in` (Cloudways ePaper, `cw-epaper`) uses the **same application** for many publisher domains. Digiti Platform lives separately at `platform.digiti.in` (`cw-platform`).

## Invariants

1. One ePaper application GUID/base URL may serve many Platform domains and many ACTIVE product tenants of the **same active product** (`EP-DI`).
2. The incoming **hostname** is the routing key. A single hostname maps to exactly one tenant at a time. No querystring, cookie, global product key or shared default tenant may select a different publisher.
3. Product-scoped ePaper machine credentials authenticate server-to-server lookups for **all** authorized EP-DI publisher hostnames. One service account per publisher is not required. The Platform resolver must verify the product GUID, application GUID, active domain/tenant/organisation and matching base URL.
4. If a canonical tenant GUID is configured, it applies **only** to the canonical host (`epaper.digiti.in`). Every customer alias must accept its separately registered tenant GUID. It must not inherit the canonical tenant.
5. ePaper's read cache uses the resolved tenant GUID; control-plane domain binding cache uses hostname, configured application/product identity and a **digest** of the current credential. A token rotation changes that binding cache namespace. The token itself is never stored in the cache key or returned in diagnostics.
6. Different tenants must never share cached editions, settings, access grants, media tokens or publisher permissions. On domain reassignment or archival, allow or purge the ePaper binding TTL and verify the next resolution before exposing customer data.
7. Canonical fallback stays disabled in production. If deliberately enabled for emergency recovery, it is restricted to the **canonical host only**, with exact product, application and tenant GUID pins. Never enable fallback to repair missing credentials.

## Example

| Incoming hostname | Application | Tenant selected |
|---|---|---|
| epaper.digiti.in | same EP-DI application | INDIGITI (canonical registration) |
| epaper.dcnnews.co.in | same EP-DI application | DCN (own active registration) |
| newspaper.publisher.example | same EP-DI application | Publisher C (own active registration) |

All rows use the same application code and Cloudways document root; `Host` and validated Platform mapping select the tenant.

## Recovery from SERVICE_AUTH_INVALID

1. Do **not** reset databases, purge publishers, create duplicate domains or change application GUIDs as a workaround.
2. In Platform > Service Accounts, check the existing EP-DI service account's ACTIVE status, product GUID and `domains.resolve` scope. Issue a new service token for the current ACTIVE product if required.
3. Put the new token **only** in the ePaper Cloudways private `config/config.php` or `EPDI_PRIVATE_CONFIG_DIR`. Redeploying Git does not update that secret.
4. Test `/health/platform` on the canonical host and **each** registered publisher hostname. Distinguish SERVICE_AUTH_INVALID, SERVICE_SCOPE_DENIED, SERVICE_PRODUCT_MISMATCH and DOMAIN_NOT_ACTIVE.
5. Once PLATFORM_BOUND, verify `/health/ready`, the correct published editions, user membership, tenant-specific media and rejection of cross-tenant access. The canonical hostname must not serve as a publisher fallback.
6. Confirm the live Cloudways branch and private configuration separately. Source/CI checks alone do not prove production readiness.

## Operational cautions

- Platform domain changes do not re-key the existing ePaper edition rows. If Platform reassignment changes a tenant GUID, reconcile local tenant ownership from backups; never bulk-rewrite GUIDs or media paths without a reviewed migration.
- Control-plane cache TTL is configurable (`platform.domain_cache_ttl`, default 600 s). A reassigned hostname can retain its previous cached binding until expiry unless the exact ePaper cache is invalidated; coordinate customer domain cutovers accordingly.
- `/health/live` and Platform's LIVE response do not establish that a publisher binding is valid. The binding-specific `/health/platform` check is authoritative for diagnostics.
