# EP-DI v2.0.0-beta.2.1 Rollback

Beta.2.1 introduces no database migration. Its cache files, Redis generations and compiled manifests are disposable acceleration artifacts; MariaDB remains canonical truth.

## Safe rollback to beta.2

1. Restore the exact `2.0.0-beta.2` application files.
2. Restart all EP-DI Supervisor workers so they no longer execute beta.2.1 `CACHE_WARM` handling/runtime code.
3. Hard-refresh/reload the Service Worker.
4. Clear beta.2.1 application cache keys/files if desired; leaving them is not data-destructive because beta.2 will not depend on the new namespaces.
5. Verify public Home/Archive/Reader, Reader OS, encrypted Offline Vault, Publisher Console, processing and scheduling.

## Database

No database rollback is needed. Do not alter the beta.2 Offline Vault schema when rolling back beta.2.1.

## Cache artifacts

The following beta.2.1 artifacts can be safely deleted during rollback if operationally desired:

- `storage/cache/runtime/*`;
- `storage/cache/compiled/editions/*` beta.2.1 revisions/pointers;
- `storage/cache/generations/*`;
- Redis keys using beta.2.1 cache/generation/manifest namespaces.

Deletion is optional because these are derived caches, not canonical business data.

## Offline Vault

Do not delete IndexedDB encrypted Offline Vault data as part of a normal beta.2.1 → beta.2 rollback. The encryption/device/license model belongs to beta.2 and remains compatible with the rollback target.
