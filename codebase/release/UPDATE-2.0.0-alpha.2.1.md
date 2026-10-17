# EP-DI v2.0.0-alpha.2.1 — Web Upgrade & Schema Doctor

This hotfix is intended for installations where the `v2.0.0-alpha.2` application files have been uploaded but the database migration may not have been applied, or where the admin dashboard/Reader Navigation is reporting schema-related errors.

## Should `database/upgrade-v2.0.0-alpha.2.sql` be run?

Yes, **an existing v1.1.9.11 / alpha.1 database must receive the alpha.2 Reader Navigation schema**. New installs using the cumulative `database/schema.sql` already contain it.

With this hotfix, you no longer need to guess or manually run SQL first. Use the secured Upgrade Center:

1. Take an on-demand Cloudways/database backup.
2. Upload the cumulative `v2.0.0-alpha.2.1` package over the existing application.
3. Sign in as canonical `OWNER` or `ADMIN`.
4. Open `/upgrade-check.php` or `/admin/system/upgrade`.
5. Review the checks.
6. Tick the backup/authorization confirmation and click **Run checks & repair missing changes**.
7. Re-run checks until the page reports **EP-DI database is aligned**.
8. Return to `/admin` and `/admin/navigation`.

## What the web doctor tests

- MariaDB connectivity and current database.
- Required v1.1.9.11 base tables.
- The v2.0.0-alpha.1 `ep_settings` scope index.
- `ep_navigation_scopes`.
- `ep_navigation_items`.
- `ep_navigation_versions`.
- Required alpha.2 navigation columns and indexes.
- Redis runtime connectivity when enabled.
- `storage/` and compiled-navigation cache writability.
- Installed build marker alignment.

## What it automatically repairs

Only known EP-DI v2 upgrade drift:

- adds the missing alpha.1 settings index;
- executes the canonical alpha.2 navigation migration when navigation tables are missing;
- adds known missing alpha.2 columns/indexes on a partially-created navigation schema;
- updates the installed build marker when writable;
- invalidates schema/dashboard/runtime caches after repair.

It uses a MariaDB advisory lock so two upgrade requests cannot modify the schema simultaneously.

## What it deliberately will NOT auto-repair

If core/base tables such as `ep_tenants`, `ep_settings`, `ep_editions`, `ep_pages`, `ep_page_assets`, `ep_processing_jobs` or `ep_system_state` are missing, the web doctor blocks automatic repair. That indicates a damaged/incomplete base installation and should be restored from the v1.1.9.11 baseline/backup rather than guessed from a web request.

## Security

The repair route is protected by all of the following:

- authenticated Digiti identity;
- infrastructure-admin role (`OWNER` / `ADMIN` by default);
- canonical ePaper SaaS hostname restriction;
- CSRF verification on writes;
- explicit backup/authorization confirmation;
- MariaDB advisory lock during repair.

`/upgrade-check.php` performs no database write itself; it only redirects to the protected Upgrade Center.

## CLI check

From SSH/application root:

```bash
php bin/schema-check.php
```

Exit code `0` means aligned; `2` means checks completed but repair/review is required; `1` means the diagnostic itself failed.

## Dashboard behavior

If known schema changes are missing, the Publisher Dashboard Attention Center now shows **Database upgrade required** and links directly to Upgrade Center. After repair, the runtime/dashboard caches are invalidated so the warning clears on refresh.
