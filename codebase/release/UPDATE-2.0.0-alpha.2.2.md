# EP-DI v2.0.0-alpha.2.2 — Upgrade Center UUID Safety Hotfix

## Why this hotfix exists

`v2.0.0-alpha.2.1` introduced the browser-based Upgrade Center. Its schema repair itself could complete successfully, but the post-repair audit call used the string `v2` as `resource_guid`. The audit table stores `resource_guid` as a UUID (`BINARY(16)`), so the audit writer threw `Invalid UUID` after the repair had already reached completion.

A second audit attempt inside the error handler repeated the same invalid conversion, producing the visible 500 page.

## What changed

- Audit logging now validates UUID-bearing fields before converting them to binary.
- Non-UUID resource identifiers such as `v2`, settings group names and probe references are stored as `metadata.resource_ref` and `resource_guid` is left `NULL`.
- The Upgrade Center schema-repair audit uses `resource_ref=schema-v2` and cannot turn a completed schema repair into a browser 500.
- A failure in the external Digiti Platform audit copy no longer breaks a locally committed request.
- Incoming `X-Correlation-Guid` is now validated with the canonical UUID validator before use.
- Upgrade Center displays `Previous schema repair recorded` when `ep_system_state.schema.v2.last_repair` exists. This is useful when the alpha.2.1 repair already completed before its audit/UI error.

## Database migration

No new database schema is introduced by alpha.2.2.

Do **not** manually rerun `database/upgrade-v2.0.0-alpha.2.sql` merely because alpha.2.1 showed `Invalid UUID`. Upload this hotfix first, reopen `/admin/system/upgrade`, and let the Schema Doctor report the actual state.

If the prior repair completed, the page should report the database aligned and show the previous repair marker. If tables/indexes are still missing, the normal repair button will safely apply only the missing known changes.

## Deployment

1. Take a Cloudways on-demand backup.
2. Upload either the full alpha.2.2 cumulative package or the alpha.2.2 upgrade-center hotfix over the existing application root.
3. Ensure the app code and `public_html` files are overwritten.
4. Open `/admin/system/upgrade`.
5. Confirm `Database schema` and the three Reader Navigation tables/indexes.
6. If repair is still offered, authorize it once.
7. Recheck `/admin/system/health`, `/admin/navigation`, and `/admin`.

## Expected outcome

The historical error `Invalid UUID` should no longer occur when running or viewing schema repair results.
