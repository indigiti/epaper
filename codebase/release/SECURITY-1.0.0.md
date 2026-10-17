# Security Model — ePaper SaaS v1.0.0

## Tenant identity
Tenant identity comes from normalized request hostname through Digiti Platform Domain Registry. The application does not accept `tenant_guid` from GET/POST/REQUEST as an ownership source.

## Data access
Tenant-owned application SQL contains `tenant_guid`. `tests/tenant-query-guard.php` scans application SQL strings and fails the build if a tenant-owned table is referenced without tenant scoping.

CLI worker/scheduler are explicit system-level cross-tenant scanners. They select a job/edition globally only to discover work, then create the corresponding tenant RequestContext before invoking business processing; writes are tenant+resource scoped.

## Media
Signed media tokens are HMAC protected and include tenant identity, asset identity and expiry. Verification requires current RequestContext tenant equality before the asset query is executed.

## Identity
Digiti Platform issues authorization codes. ePaper SaaS uses `state` + PKCE, exchanges server-side, stores a host-local session and activates only the membership matching the domain-resolved organisation.

## CSRF
Mutation routes use session CSRF verification. Machine Platform calls use service tokens/scopes rather than browser CSRF.

## HTTP
The common HTTP layer applies CSP, frame protection, nosniff, referrer and related security headers. Production cookies are HttpOnly/Secure where HTTPS is active.

## Secrets
`config/config.php` is generated during installation and is intentionally excluded from the package. Platform service token, database password and Redis password must never be committed to release ZIP source manifests.
