# EP-DI v2.0.1.8.1 Schema Safety Hotfix

Apply this patch over EP-DI v2.0.1.8.

## Production incident fixed

`/admin/subscribers` could throw PDO SQLSTATE 42S02 when the v2.0.1.8 code was deployed before `database/upgrade-v2.0.1.8-trusted-app-desktop-login.sql` was executed.

The hotfix makes Subscribers degrade safely and extends Upgrade Center / Schema Doctor through the v2.0.1.5, v2.0.1.7 and v2.0.1.8 reader-auth migrations.

## After upload

1. Take a current database backup.
2. Open `/admin/system/upgrade`.
3. Run the safe schema repair.
4. Re-open `/admin/subscribers`.

For the exact missing `ep_reader_devices` incident, executing `database/upgrade-v2.0.1.8-trusted-app-desktop-login.sql` directly once is also sufficient.
