# EP-DI v2.0.0-beta.1.2 Build Manifest

- Version: `2.0.0-beta.1.2`
- Base / required version: `2.0.0-beta.1.1`
- Release type: cumulative beta hotfix
- Feature: Processing Control & Recovery Profiles
- Database migration: **none**
- Latest required schema: `database/upgrade-v2.0.0-beta.1.1.sql`
- Worker restart: **required**
- Service-worker cache: `epdi-v2.0.0-beta.1.2`

## Runtime files

- `app/App.php`
- `app/Services/SettingsCatalog.php`
- `app/Services/ProcessingRecovery.php`
- `app/Services/ProcessingPipeline.php`
- `app/Services/Processing.php`
- `app/Services/QueueService.php`
- `public_html/assets/admin-beta.css`
- `public_html/assets/admin-beta.js`
- `public_html/service-worker.js`
- `public_html/offline.html`

## Functional guarantees

- Existing beta.1.1 Editions actions and scheduling remain intact.
- Existing Publisher Control processing values remain valid.
- WebP qualities are runtime-wired, not decorative UI controls.
- Automatic rescue is opt-in, bounded and unpublished-edition-only.
- Manual custom/recommended reprocessing remains one-off and history preserving.
- No new RabbitMQ lane or Supervisor process is introduced.
- No database migration is introduced.

## Release gates

- beta.1.2 build guard
- Processing Control static guard
- beta.1.1 Editions Operations regression guard
- Supervisor runtime guard
- Schema Doctor guard
- Audit UUID safety guard
- tenant SQL isolation guard
- Reader parity guard
- cumulative v2 guard
- PHP / JavaScript syntax validation
