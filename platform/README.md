# Digiti Platform v1.0.0

Digiti Platform is the shared SaaS control plane intended for `platform.digiti.in`. It is deliberately separate from City ePaper and future product databases.

## Included phases

- Platform foundation: organisations, global identities, memberships, platform admin, UUIDv7, Request GUIDs, RBAC catalogue, MFA/session security, audited support impersonation, audit, errors, Redis, jobs/outbox, health.
- Product registry: products, applications, product tenants, service accounts and application heartbeats.
- Commercial core: features, plans, prices, subscriptions, billing state vs service access state, entitlement grants and usage meters.
- Identity foundation: central authorization-code + PKCE flow suitable for `auth.digiti.in`, persistent session registry, TOTP MFA support and product-server token exchange.
- Domain layer: global hostname uniqueness, DNS verification, domain → product/application/tenant routing, Redis domain cache, manual edge activation and optional Cloudflare Custom Hostname provider.
- Billing foundation: invoices, invoice items, payments, immutable attempts, refunds, webhook receipt/deduplication, Manual/Razorpay/Stripe provider adapters and reconciliation queue hooks.
- Operations: worker, scheduler, outbox, jobs, service health, platform error/audit views.

## Cloudways layout

Deploy the package into the application root so Cloudways serves `public_html/` as the web root:

```text
application/
├── app/
├── bin/
├── config/
├── database/
├── public_html/       ← web root
├── release/
├── storage/
└── VERSION
```

Open:

```text
https://platform.digiti.in/install.php
```

The installer creates the schema, platform administrator, City ePaper product, initial ePaper application registration, namespaced feature catalogue and Starter/Professional/Enterprise plans.

After successful installation `storage/install.lock` prevents the installer from running again.

## Required PHP extensions

- PDO MySQL
- OpenSSL
- JSON
- cURL for internal service/product calls and Cloudflare provider
- Redis extension recommended
- intl recommended for IDNA hostname normalization

## Recommended hosts

```text
platform.digiti.in    Platform Admin/control plane
auth.digiti.in        Central identity surface; can initially point to this same app
domains.digiti.in     Edge/CNAME target
epaper.digiti.in      Canonical City ePaper application
```

`platform.digiti.in` and `auth.digiti.in` may initially use the same application code. Customer domains never share cookies with Digiti domains; the supplied auth flow uses redirects, one-time authorization code and PKCE.

## First ePaper tenant onboarding

1. Platform Admin → Organisations → create `Pune Mirror Media`.
2. Platform Admin → Product Tenants → map the organisation to `City ePaper`.
3. Platform Admin → Applications → verify/register `city_epaper` and its OAuth callback.
4. Platform Admin → Service Accounts → create the ePaper machine token with:

```text
domains.resolve,
tenant.read,
access.read,
entitlements.read,
identity.verify,
impersonation.consume,
usage.write,
heartbeat.write,
auth.exchange,
audit.write,
errors.write
```

5. Platform Admin → Subscriptions → activate a plan for the ePaper product tenant.
6. Platform Admin → Domains → add `epaper.punemirror.com`.
7. Customer configures the displayed CNAME/TXT record.
8. Verify the domain, provision TLS/edge routing, then activate it.
9. Configure the City ePaper Platform Adapter with the ePaper OAuth client ID, service token and tenant mapping.

Repeat for `epaper.civicmirror.in` or any other publisher.

## Internal service API

All product-facing internal endpoints require `X-Digiti-Service-Token` and an appropriate service-account scope.

```text
GET  /internal/v1/domains/resolve?hostname=...
GET  /internal/v1/tenants/{guid}/entitlements
POST /internal/v1/usage
POST /internal/v1/heartbeats
POST /internal/v1/audit
POST /internal/v1/errors
POST /internal/v1/auth/token
```

Public provider webhooks:

```text
POST /webhooks/razorpay
POST /webhooks/stripe
```

## Domain status model

```text
PENDING
→ AWAITING_DNS
→ ROUTING_VALID
→ SSL_PROVISIONING
→ ACTIVE

REVALIDATION_FAILED
SUSPENDED
```

With `edge.provider=manual`, activate only after you have provisioned the hostname/TLS at Cloudflare/Cloudways. With the optional Cloudflare provider configured, the platform can create/check Custom Hostnames; the account still requires a correctly configured Cloudflare for SaaS zone/fallback origin.

## Workers

One-shot worker:

```bash
php bin/worker.php
```

Supervisor process:

```bash
php bin/worker.php --loop
```

Scheduler should run once per minute:

```bash
php bin/scheduler.php
```

## Health

```text
GET /health/live
GET /health/ready
```

CLI:

```bash
php bin/health.php
```

## Security boundary

Platform Administrator is intentionally different from organisation membership/product administration. Product applications must enforce their own tenant membership against the `product_tenant_guid` supplied by domain resolution.

No browser request is allowed to choose a tenant by arbitrary `tenant_guid` request parameter.

## Billing note

The schema, webhook verification, immutable attempts/refunds and provider abstraction are implemented. Manual billing is immediately usable. Razorpay/Stripe adapters validate configured webhook signatures and expose the provider contract; live payment-order/PaymentIntent creation remains an explicit product/payment-service network operation after provider credentials and business checkout flows are configured. This prevents the platform package from fabricating merchant-specific checkout behavior.
