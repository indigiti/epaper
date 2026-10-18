# Platform Cloudways multilayer — staging acceptance

Candidate: PR #32, based on cw-platform. This is NOT production authorization.

## Automated evidence
- PHP 8.3 lint, reflection method contract, injectable domain controller unit tests.
- Source assertions: admin auth before CRUD, POST CSRF guard, scoped internal
  domain resolution, active organisations/tenants/products/applications.
- Ephemeral PHP server GET /health/live, GET /login, anonymous GET /admin
  redirect and POST /login missing-CSRF 419. No production host is contacted.

## Prerequisites (isolated staging only)
- Independent Cloudways app, hostname, MariaDB, Redis namespace and session store.
- Staging super-admin + separate operator account with MFA test identity;
  disposable organisation/tenant/domain and ePaper staging client credentials.
- Separate Cloudflare sandbox or manual DNS edge provider; never modify production DNS.
- Confirm initial/rollback database schema snapshots and protected private config.

## Must-pass authenticated checks
- Correct password login, incorrect password, MFA, logout, session expiry and
  stale CSRF; no admin operations accessible without role+CSRF.
- Create/edit/disable/remove organisation, product tenant, application,
  service account, subscription, plan and domain.
- Domain DNS challenge, duplicate hostname rejection, normalization, verification,
  edge activation, TLS readiness, renewal/disabling/revocation and audit logging.
- Service-scoped domain resolver (valid vs invalid service token), tenant lookup
  and entitlement response, cross-tenant isolation and cache invalidation.
- Suspend/reactivate tenant while testing ePaper reader denial/recovery; confirm
  Redis fallback and outage behavior does not erroneously reopen suspended access.
- Webhook idempotence with provider test fixtures, billing reconciliation and
  role-limited admin screens; no live payments.
- Verify response/code parity, access logs, request IDs, audit logs, and DB records.
- Rollback on isolated Cloudways staging and compare integrity with baseline.

## Promotion rule
Do not merge PR #32 or pull cw-platform to Cloudways until authenticated
staging evidence and security/rollback gates are signed off. Green CI is
source parity evidence only. Keep staging/production credentials distinct.
