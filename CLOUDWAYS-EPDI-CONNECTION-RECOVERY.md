# Restore epaper.digiti.in after Platform EP-DI registration

**Situation:** Digiti Platform marks \`epaper.digiti.in\` ACTIVE under product \`EP-DI\`, organisation \`INDIGITI\` and its active publisher tenant, but the ePaper web application shows "Request needs attention".

## Root causes and fixes

- The ePaper \`app/Services/DomainResolver.php\` previously required the literal product key \`EPAPER\`. It therefore rejected the new legitimate Platform product \`EP-DI\` with \`Domain is not registered for EPAPER\`. The new code uses the application Base URL, host, product GUID, application GUID and tenant GUID instead.
- The previous error view could attempt tenant-dependent rendering before domain/tenant context was established, masking the original error behind HTTP 500. The new diagnostic is a standalone HTTP 503 page.
- Old Redis and filesystem cache entries could still carry the archived EPAPER tenant mapping. The new resolver uses a versioned binding namespace and validates cached identities before using them.
- A previous canonical default tenant setting could silently serve old content if Platform lookup failed. This fallback is disabled unless explicitly enabled AND pinned to the exact product, application and tenant GUIDs.
- Platform's six onboarding checks do not prove that the separate ePaper application can authenticate to Platform or read its local edition database. \`/health/platform\` now checks this explicitly.

## Cloudways operation — no fresh installation, no database reset

1. Take and verify a backup of the **ePaper Cloudways application** (private \`config/\`, local MariaDB and uploads) and the separate **Platform** application. Preserve the existing published newspaper files.
2. Deploy the reviewed ePaper release from **\`cw-epaper\` to the ePaper application only**. The Platform application continues to use \`cw-platform\`. Keep the Cloudways ePaper webroot pointed to the nested \`public_html\`.
3. Securely inspect the ePaper installation's private \`config/config.php\` (or its configured \`EPDI_PRIVATE_CONFIG_DIR\` if used). Do not paste secrets into chat, GitHub or browser URLs. The correct values must reflect the existing Platform records:
   - \`app.url\` = \`https://epaper.digiti.in\`
   - \`platform.url\` = \`https://platform.digiti.in\`
   - \`platform.product_key\` = \`EP-DI\` (informational; not used for authorization)
   - \`platform.product_guid\` = EP-DI product GUID, if provided to pin ownership
   - \`platform.application_key\` = the actual application key from Platform > Applications
   - \`platform.application_guid\` = the current **EP-DI application GUID**, not the old archived EPAPER application
   - \`platform.oauth_client_id\` = current EP-DI application's OAuth client ID
   - \`platform.service_token\` = service-account token bound to the **EP-DI product** and authorized for \`domains.resolve\`, \`tenant.read\`, \`access.read\`, \`entitlements.read\`, \`identity.verify\`, \`auth.exchange\`, \`heartbeat.write\` and the other scopes needed by ePaper. The old EPAPER product-scoped token is not valid for EP-DI.
   - \`platform.default_tenant_guid\` = current INDIGITI/EP-DI tenant GUID, if configured; do not leave an old archived tenant here
   - \`platform.default_organisation_guid\` = current INDIGITI organisation GUID, if configured
   - \`platform.allow_canonical_fallback\` = \`false\` (recommended)
   Updating credentials in the private configuration is a deliberate operational action, never an automatic Git or SQL migration.
4. Browse \`https://epaper.digiti.in/health/live\`. It must return \`LIVE\`; this checks the PHP application responds but does NOT prove Platform binding.
5. Browse \`https://epaper.digiti.in/health/platform\`. Require \`ok:true\`, \`status:PLATFORM_BOUND\`, \`diagnostic.binding.ok:true\`, \`diagnostic.domain.product_key:"EP-DI"\`, \`diagnostic.tenant.status:"ACTIVE"\` and the expected tenant GUID. A 503 response includes \`recommended_action\` such as \`CONFIGURED_APPLICATION_GUID_STALE\`, \`CONFIGURED_PRODUCT_GUID_STALE\`, \`APPLICATION_BASE_URL_MISMATCH\`, \`PLATFORM_RESOLVE_FAILED\` or \`CHECK_PUBLISHER_TENANT\`. Use these codes to correct the *same* records, not to create new ones.
6. Browse \`https://epaper.digiti.in/health/ready\` and note failed dependency checks (database, storage, Redis, Platform publisher binding, imaging tools/queue). Fix any service required by the homepage.
7. Finally check \`https://epaper.digiti.in/\`, a published edition, publisher login, and the expected INDIGITI editions. Confirm a separate DCN domain resolves to its own product tenant and cannot read INDIGITI data.
8. If the homepage still shows a generic error, look up the displayed **Error ID** in the ePaper application's private \`ep_error_events\` table or Cloudways PHP error log. That proves which PHP exception remains. No customer-data deletion is justified by a 500.

## Data preservation caveat

Platform's new EP-DI product tenant may have a different GUID from the previous EPAPER/AMG tenant. The ePaper MariaDB tables scope publications, editions and media to their original tenant GUIDs. Rebinding Platform does **not** automatically migrate editions. If binding succeeds but the site is empty, compare local \`ep_tenants\` and publication/edition tenant GUIDs on a backup. Any migration must preserve authorship, media pointers and isolation; do not mass-rewrite tenant GUIDs without an audited migration.

## Git/source parity

The repo stores two ePaper deployable source layouts (\`app/\` etc and \`codebase/app/\` etc). They must remain byte-identical and both pass \`branch-layout-parity.py\`. A passing Platform \`cw-platform\` CI check never validates the separate ePaper deployment.

**Status:** GitHub release and synthetic integration checks can verify code changes, but cannot read or change a private Cloudways config, confirm which branch is currently deployed, or guarantee production uptime.
