# Digiti Platform Setup for ePaper SaaS

Digiti Platform must already be installed and healthy at `https://platform.digiti.in`.

## A. Register / confirm product

Platform Admin -> Products

```text
Product key: EPAPER
Name: ePaper SaaS
Status: ACTIVE
```

## B. Register application

Platform Admin -> Applications

```text
Product: EPAPER
Application key: city_epaper
Name: ePaper SaaS
Base URL: https://epaper.digiti.in
Admin URL: https://epaper.digiti.in/admin
Health URL: https://epaper.digiti.in/health/ready
OAuth callback: https://epaper.digiti.in/auth/callback
```

Copy the generated Application GUID and OAuth Client ID into the ePaper SaaS installer.

For each customer-owned domain, the OAuth redirect URI used by that host must be allowed in Digiti Platform before login can complete. The current Platform application registry supports registered redirect URIs; add the customer callback as part of domain onboarding.

## C. Service account

Platform Admin -> Service Accounts. Bind it to the EPAPER product and use these scopes:

```text
domains.resolve,
tenant.read,
access.read,
entitlements.read,
identity.verify,
usage.write,
heartbeat.write,
auth.exchange,
audit.write,
errors.write
```

`impersonation.consume` can be added later if support impersonation is enabled in the product UI.

Copy the generated token immediately and store it only in `config/config.php` through the installer. Do not put it in source control.

## D. Create the first organisation / product tenant

Example:

```text
Organisation: Pune Mirror Media
Product: EPAPER
Product tenant: Pune Mirror ePaper
```

Create/activate a subscription so `service_access_state` is `FULL` and assign the intended EPAPER entitlements.

## E. Register the customer domain

Example:

```text
epaper.punemirror.com
```

Bind:

```text
Organisation -> Pune Mirror Media
Product -> EPAPER
Application -> ePaper SaaS / city_epaper
Product tenant -> Pune Mirror ePaper
```

Complete the Platform DNS verification and routing/SSL lifecycle. The domain resolver accepts domains whose Platform status is `ROUTING_VALID`, `SSL_PROVISIONING`, or `ACTIVE`; production traffic should normally be `ACTIVE`.

Repeat for:

```text
epaper.civicmirror.in
...
```

## F. User / publisher administrator

Create a global Platform user and active organisation membership. Roles accepted by ePaper SaaS publisher admin in v1.0.0 are:

```text
OWNER
ADMIN
PUBLISHER_ADMIN
EDITOR
OPERATOR
```

The customer host determines which organisation membership is activated. A user cannot use a membership from another organisation to enter the current publisher Admin.

## G. Entitlements

Recommended product feature keys include:

```text
EPAPER.PUBLICATIONS
EPAPER.ADMIN_USERS
EPAPER.STORAGE_GB
EPAPER.EDITIONS
EPAPER.OCR_PAGES
EPAPER.OCR_SEARCH
EPAPER.SMART_ARTICLE
EPAPER.CLIPPING
EPAPER.OFFLINE
EPAPER.ARTICLE_MODE
EPAPER.AD_FREE
EPAPER.ADVANCED_ANALYTICS
PLATFORM.CUSTOM_DOMAINS
```

The product code checks entitlements rather than plan names.
