# Rollback — EP-DI v2.0.0-rc.1.2

RC.1.2 contains no database migration, so rollback is application-file only.

## Preferred rollback
1. Put the application in the normal maintenance/deployment window if required.
2. Restore the verified `v2.0.0-rc.1.1` application package/files.
3. Hard-refresh once so the RC.1.1 service worker is reactivated.
4. Verify `/`, `/archive`, `/admin`, `/admin/editions`, Reader OS and one published Reader edition.

No database rollback, queue repair or Supervisor reconfiguration is required.

## Data safety
RC.1.2 does not mutate edition data, assets, publication state, permissions or semantic-news records. The new Archive calendar is a cached read model only.
