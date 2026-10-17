# Digiti Platform v1.0.2 — Trusted Direct Authentication

- Added service-authenticated `POST /internal/v1/auth/direct` for first-party product login screens.
- Requires new `auth.direct` service-account scope.
- Validates application, product, product tenant, organisation and service-account product binding before credential verification.
- Preserves MFA, rate limiting, generic credential errors and audit events.
- No password hashes or credentials are returned to product applications.
- Existing OAuth Authorization Code + PKCE remains available for compatibility and future cross-product SSO.
- No database schema migration.

# Changelog

## 1.0.1 — Admin Management CRUD

- Renames the seeded/display product **City ePaper** to **ePaper SaaS** while preserving the stable `EPAPER` product key.
- Renames the EPAPER application display name to **ePaper SaaS** while preserving application key `city_epaper` and OAuth/client integrations.
- Adds Edit / Update / Activate / Deactivate / Remove controls for Organisations, Global Users, Products, Applications, Product Tenants and Domains.
- Adds Edit / Revoke / Activate / Rotate Token / Remove controls for Service Accounts.
- Adds Plan, Price and Feature catalogue management under Commercial.
- Adds Subscription lifecycle editing for billing state, service access, period end and cancel-at-period-end.
- Adds safe invoice Void action; financial records are never deleted.
- Adds a persistent secondary management navigation for Users, Applications, Product Tenants, Service Accounts, Subscriptions, Billing, Audit, Errors and Health.
- Adds confirmation prompts for destructive/deactivation actions.
- Hardened domain resolution so removed/suspended products, applications, tenants or organisations cannot continue to resolve through an otherwise-active domain.
- Adds administrator-only `/update.php` upgrade helper for existing v1.0.0 installations.
- No schema changes.

## 1.0.0

Initial coordinated Digiti SaaS control-plane baseline.
