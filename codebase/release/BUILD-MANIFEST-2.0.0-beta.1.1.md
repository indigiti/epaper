# EP-DI v2.0.0-beta.1.1 Build Manifest

- Version: `2.0.0-beta.1.1`
- Base: `2.0.0-beta.1`
- Release name: **Editions Operations**
- Database migration: **Required**
- Migration: `database/upgrade-v2.0.0-beta.1.1.sql`
- Preferred upgrade path: `/admin/system/upgrade`
- Runtime foundation: MariaDB + Redis + RabbitMQ + Supervisor retained
- New service: `app/Services/ProcessingRecovery.php`
- Primary updated screen: `/admin/editions`
- New edition operations: contextual Reprocess, Publish Now, Schedule, Unschedule, Publish When Ready
- Processing recovery: default/custom/recommended one-off job profiles; global Publisher Control settings remain unchanged
- Scheduler health: exposed in `/admin/system/health`
- Service-worker build cache: `epdi-v2.0.0-beta.1.1`
- Direct rollback baseline: `2.0.0-beta.1` plus pre-migration database backup

## Schema additions

- `ep_editions.publish_mode`
- `ep_editions.schedule_timezone`
- `ix_edition_publish_schedule`
- `ix_processing_edition_type_created`

## Required release gates

- PHP syntax validation
- JavaScript syntax validation
- beta.1.1 build guard
- Editions Operations static guard
- Supervisor runtime guard
- Schema Doctor guard
- Audit UUID safety guard
- tenant SQL isolation guard
- Reader parity guard
- cumulative v2 guard
- internal SHA-256 manifest
- ZIP integrity and external SHA-256
