# Digiti Product Integration Contract v1

Future SaaS products should integrate with Digiti Platform through stable contracts rather than sharing Platform database tables directly.

## Product identity

Each product registers:

```text
product_guid
product_key
application_guid
application_key
base_url
admin_url
health_url
oauth_client_id
redirect URI(s)
```

## Machine authentication

Product services use `X-Digiti-Service-Token`. Store only the token hash at Platform. Recommended scopes are granted per product.

## Standard context

A product request should resolve and carry:

```text
request_guid
correlation_guid
hostname
domain_guid
organisation_guid
product_guid
application_guid
product_tenant_guid
user_guid
membership_guid
impersonator_user_guid
service_access_state
entitlements
```

## Internal APIs

```text
GET  /internal/v1/domains/resolve
GET  /internal/v1/tenants/{guid}
GET  /internal/v1/tenants/{guid}/access
GET  /internal/v1/tenants/{guid}/entitlements
POST /internal/v1/memberships/verify
POST /internal/v1/impersonation/consume
POST /internal/v1/usage
POST /internal/v1/heartbeats
POST /internal/v1/audit
POST /internal/v1/errors
POST /internal/v1/auth/token
```

## Tenant isolation

Platform identifies the tenant, but the product application remains responsible for scoping its own business database by `product_tenant_guid`/local `tenant_guid` on every tenant-owned operation.

## Failure policy

- Domain cache: fall back to Platform resolver.
- Entitlement cache: a product may use a bounded stale cache according to its safe access policy.
- Tenant/business data: never infer/fail open to another tenant.
- Security-sensitive service access: deny when tenant context cannot be proven.
