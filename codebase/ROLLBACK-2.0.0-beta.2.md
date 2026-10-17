# EP-DI v2.0.0-beta.2 Rollback

Beta.2 adds Offline Vault database structures and changes the device-awareness of offline edition uniqueness. Prefer an application rollback that **leaves the additive beta.2 schema in place** rather than dropping vault tables/columns during an incident.

## Safe rollback sequence

1. Keep the site online if possible and prevent new offline downloads while rollback is being prepared.
2. Restore the `2.0.0-beta.1.2` application files.
3. Do **not** drop `ep_offline_devices`, `ep_offline_licenses` or the beta.2 `ep_offline_items` columns/indexes unless restoring the complete database from the pre-beta.2 backup.
4. Hard-refresh/reload the service worker.
5. Verify public Reader, Publisher Console, processing and scheduling.
6. If beta.1.2 legacy offline behavior is intentionally restored, treat any plaintext browser cache path as a temporary compatibility state only and plan an immediate return to beta.2 or later.

## Browser vault after rollback

Ciphertext stored in the beta.2 IndexedDB vault is harmless to beta.1.2 because older code does not know how to use it. A user can clear site data manually, or the browser data can remain until beta.2+ is restored.

If a security incident requires immediate local-vault invalidation, revoke the affected device/license server-side before application rollback or restore the pre-beta.2 database backup according to the incident-response plan.

## Full rollback

A full database rollback must use the Cloudways/database backup taken immediately before beta.2 deployment. Do not manually reverse the device-aware unique index or delete license/device tables on a live database unless a tested migration rollback has been prepared for the exact production state.
