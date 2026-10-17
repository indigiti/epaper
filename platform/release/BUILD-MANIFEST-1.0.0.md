# Digiti Platform v1.0.0 — Build Manifest

## Purpose

Install-ready central control plane for `platform.digiti.in`, designed to register and operate City ePaper plus future Digiti SaaS products.

## Implemented phase map

### Foundation
- Cloudways browser installer and installer lock.
- PHP 8.4-oriented custom PHP modular application.
- MariaDB schema using UUIDv7/BINARY(16) identifiers.
- Redis cache/lock layer.
- Platform Admin separate from organisation/product membership.
- Organisations, global identities, memberships and RBAC catalogue.
- Request GUIDs, security headers, login throttling, session registry and TOTP MFA.
- Audit, Error Center, outbox, jobs, worker, scheduler and health.
- Audited one-time Platform support impersonation grants.

### Product integration
- Product registry.
- Application registry and version/heartbeat records.
- Product tenants.
- Service/machine accounts with hashed scoped tokens.
- Generic internal API contracts suitable for additional SaaS products.

### Commercial core
- Feature catalogue.
- Plans and prices.
- Subscription billing state separately from service-access state.
- Plan entitlements plus tenant grants.
- Usage meters and remaining-limit calculation.

### Identity
- Central authorization code + PKCE.
- Multi-domain redirect URI registry.
- Persistent identity sessions.
- MFA support.
- Tenant membership verification API.

### Custom domains
- Global normalized hostname uniqueness.
- Domain → organisation/product/application/product-tenant registry.
- DNS CNAME/TXT verification.
- Redis domain resolver.
- Manual edge/TLS activation.
- Optional Cloudflare Custom Hostname provider.
- Revalidation worker.

### Billing
- Invoices and invoice items.
- Payments and immutable payment attempts.
- Refunds.
- Provider webhook receipt, verification and dedupe.
- Manual, Razorpay and Stripe provider interfaces.
- Payment reconciliation job foundation.

## Files intentionally not present

- No production `config/config.php` or credentials.
- No vendor dependencies.
- No City ePaper Reader/business files.

## Runtime verification limitation

The build workspace has PHP 8.4, but it does not provide `pdo_mysql`, `curl`, or `redis` extensions and has no MariaDB/Redis service configured. Therefore this package was syntax/static-regression tested here, while the browser installer performs the real Cloudways extension/DB/Redis checks before allowing installation.
