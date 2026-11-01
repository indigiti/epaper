# cw-platform: operator actions, recoveries, and staging reset

Scope: **only** the \`cw-platform\` deployment. Do not deploy Platform changes from \`main\` or \`cw-epaper\`.

## Operator controls

- Most core registries now show a row action toolbar: **Edit**, **Activate/Deactivate** as appropriate, and **More → Archive**.
- Archived (\`REMOVED\`) records show **Restore** instead of activation.
- Restore never hard-deletes or recreates a record. It preserves the original unique code/hostname, audit history, related financial records and permissions.
- Restored organisations, users, products, applications, product tenants and plans enter \`SUSPENDED\`, not \`ACTIVE\`.
- Restored service accounts enter \`REVOKED\`, and require a fresh token rotation before use. Previously issued secrets are not silently trusted.
- A restored customer hostname enters \`AWAITING_DNS\`, resets DNS/TLS/go-live evidence and requires an independent operational approval. Its parent organisation, tenant, product and application must exist and not be removed.
- Canonical/system application domains cannot be archived/restored through the customer-domain action bar.
- Remove/Archive is not physical deletion. It keeps foreign-key relationships and historical audit data.
- Unreferenced **features** remain an explicit hard-delete exception in Advanced Commercial: the code checks referenced plans, grants and usage first. Retention policy should be reviewed before using it.

## Staging-only reset

A reset button is **not** enabled on \`https://platform.digiti.in/\`. Previous screenshots show that host as PRODUCTION. Back up both Platform and the separate ePaper runtime before making any production changes.

1. Create a **separate** Cloudways staging clone of the Platform app, with an independent database, host, credentials and media/runtime storage. Do not point staging at the production database or production domains.
2. Verify the clone can be independently restored from backup.
3. In the **private staging** runtime config under \`'app'\`, set:
   \`\`\`php
   'env' => 'staging',
   'allow_staging_reset' => true,
   \`\`\`
4. Sign in as a Platform administrator on the **staging hostname**. In **Advanced administration**, open **Staging reset** (\`/admin/staging-reset\`).
5. Only accounts with organisation codes prefixed \`TEST_\` or \`DEMO_\` are eligible. Each domain must be a customer domain and not the canonical application host.
6. The page previews the matching domains. Enter the exact test hostname and check the acknowledgement to reset **one domain’s onboarding lifecycle**.

What the reset does: sets \`AWAITING_DNS\`, clears DNS/edge/TLS verification and activation timestamps, clears the Platform domain cache, invalidates prior go-live attestations and records the operator in the audit log.

What it **does not** do: delete tenants, organisation records, users, credentials, publications, images, subscriptions, financial transactions, application data or audit history. Original unique hostnames remain reserved. For a **complete staging database/media wipe**, restore a verified clean staging snapshot **inside the isolated staging application** instead of introducing a production-capable "delete all" endpoint.

The reset endpoint is disabled unless **all** conditions hold:
- \`app.env\` equals \`staging\`, \`testing\` or \`development\`
- \`app.allow_staging_reset\` is exactly \`true\`
- request hostname is present and is **not** \`platform.digiti.in\`, \`epaper.digiti.in\` or \`domains.digiti.in\`
- request is authenticated by a Platform administrator
- an eligible test domain is selected and the operator confirms its hostname.

**Production acceptance:** run PHP syntax and contract checks, verify all actions on a staging snapshot, test archived-domain recovery, cross-tenant isolation and both live EPAPER hostnames, then deploy only the reviewed \`cw-platform\` revision to the Platform application. No production data reset is part of this release.
