# EP-DI v2.0.0-beta.1.1 — Editions Operations

## Release intent

Beta.1.1 extends the verified beta.1 Publisher Console with a focused Editions operations workflow. It keeps the alpha.2.3 MariaDB/Redis/RabbitMQ/Supervisor runtime and the beta.1 console design system intact while making recovery and scheduled publishing first-class operations.

This release **does include a database migration**. Existing beta.1 deployments should use the secured Upgrade Center after the files are deployed rather than blindly re-running SQL.

## Editions workspace

`/admin/editions` now uses an operations-oriented table:

- Edition
- Date
- Processing
- Publish status
- Schedule
- Actions

The row action hierarchy is intentionally contextual:

- View and Edit stay immediately available.
- Failed / `NEEDS_ATTENTION` editions expose **Reprocess** as the prominent recovery action.
- Draft editions expose Process Now.
- Active editions link to Processing history/progress.
- Ready editions expose Publish / Schedule.
- Less-used actions such as archive, restore, cancel schedule and optional reprocess remain under the More menu.

Search, status and edition-date filtering remain on the same screen. The list is backed by one tenant-scoped admin query that returns the latest root processing job and processing-run summary, avoiding row-by-row processing lookups.

## Reprocessing / recovery

A failed unpublished edition can be reprocessed without overwriting its prior job history.

The Reprocess drawer supports:

1. **Use default settings** — retry with current Publisher Control defaults.
2. **Customize & reprocess** — one-off DPI, preview size, thumbnail size, Deep Zoom tile size, OCR timeout/languages and language-profile controls.
3. **Automatically apply recommended corrections** — a conservative error-aware recovery profile for geometry/memory/render/OCR timeout signatures.

One-off recovery values are carried in the new processing job payload and flow through render, assets, OCR and finalize stages. They do **not** modify global Publisher Control settings.

Reprocessing is rate-limited and refused while an active root processing job already exists. Direct reprocessing of a live `PUBLISHED` edition is deliberately blocked in beta.1.1 because the current processing pipeline replaces page/assets in place. A future staged/atomic replacement workflow is required before live-edition reprocessing can be considered safe.

Existing processing history remains available at `/admin/processing/{edition-guid}`.

## Publishing intent and scheduling

The Add Edition drawer now presents three explicit choices:

- **Save as draft** — process now, publish manually later.
- **Publish when ready** — process now, then publish automatically only after the edition reaches `READY`.
- **Schedule publishing** — process now and publish at/after the chosen local date/time, but only after processing has successfully reached `READY`.

Ready editions can also be published immediately or scheduled from the Editions table. A scheduled edition can be unscheduled without deleting its processing/audit history.

Publishing intent is persisted separately from processing state using `publish_mode`; this prevents an expired schedule from publishing a failed or half-processed edition.

## Scheduler safety

`bin/scheduler.php` now handles:

- `AUTO_READY` editions once their processing state becomes `READY`.
- `SCHEDULED` editions only when the selected time has arrived **and** the edition is ready.
- cache invalidation after automatic publication.
- the existing stale-worker recovery and platform heartbeat behavior.

System Health now includes a **Publishing scheduler** heartbeat. The existing scheduler cron should remain enabled every minute. The target health state is a scheduler heartbeat no older than 150 seconds.

## Database change

New edition fields:

- `publish_mode VARCHAR(24) NOT NULL DEFAULT 'MANUAL'`
- `schedule_timezone VARCHAR(64) NULL`

New indexes:

- `ep_editions.ix_edition_publish_schedule (tenant_guid,publish_mode,status,scheduled_at)`
- `ep_processing_jobs.ix_processing_edition_type_created (tenant_guid,edition_guid,job_type,created_at)`

Canonical migration:

`database/upgrade-v2.0.0-beta.1.1.sql`

The Schema Doctor/Upgrade Center understands these additions and can add only missing columns/indexes under its existing MariaDB advisory lock.

## Deployment from v2.0.0-beta.1

1. Keep the working beta.1 package/server backup as rollback.
2. Take a current Cloudways database backup.
3. Extract the beta.1.1 hotfix or full package over the application root.
4. Open `/admin/system/upgrade`.
5. Confirm the beta.1.1 publishing fields/indexes are shown as `FIXABLE` if missing.
6. Run the Upgrade Center repair once.
7. Open `/admin/editions` and verify View/Edit/Reprocess/Publish/Schedule actions.
8. Verify `/admin/system/health` shows **Publishing scheduler — Healthy**.
9. Test one READY edition with a schedule a few minutes in the future before using production scheduling broadly.

Do not manually run the beta.1.1 SQL first when Upgrade Center is available. The canonical SQL is included for clean deployment/SSH recovery, while Schema Doctor provides the safer state-aware web upgrade path.

## Runtime / worker boundary

No new RabbitMQ queue or Supervisor lane is introduced. The verified worker target remains:

- Outbox 1
- Render 1
- Assets 2
- OCR 1
- Finalize 1

The new workflow therefore does not change the working alpha.2.3 worker topology.

## Release boundary

Beta.1.1 does not implement the encrypted Offline Vault or semantic News/AI SEO layer. Those remain later v2 milestones.
