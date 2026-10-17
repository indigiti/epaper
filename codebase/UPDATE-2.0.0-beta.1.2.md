# EP-DI v2.0.0-beta.1.2 — Processing Control & Recovery Profiles

## Purpose

This focused beta extends the existing Publisher Control → Processing screen without replacing the working beta.1.1 Editions/processing workflow. It exposes real runtime quality defaults that were previously hard-coded and adds a bounded, opt-in automatic recovery policy for unpublished editions.

## What changed

### Publisher Control → Processing
`/admin/settings/processing` now groups the existing processing defaults into clearer operational cards:

- **PDF Processing** — WebP delivery master, PDF render DPI, high-resolution WebP quality.
- **Generated Assets** — preview/thumbnail widths, Deep Zoom tile size, preview/thumbnail/tile WebP quality.
- **OCR** — language profile, per-page timeout, automatic edition-language profile.
- **Processing Fallback** — preserves the existing renderer/RabbitMQ/Supervisor fallback model and shows normal retry counts with a link to Infrastructure.
- **Recovery & Reprocessing** — manual smart-correction default, optional automatic rescue, rescue limit and configurable recovery DPI/tile/OCR ceilings.

Safe presets are provided for **Recommended**, **High fidelity**, and **Low-memory recovery**. Presets only modify the browser form and are not persisted until **Save Processing** is pressed.

### Runtime quality settings
The RabbitMQ pipeline and legacy fallback processor now use publisher settings for:

- `processing.highres_quality`
- `processing.preview_quality`
- `processing.thumbnail_quality`
- `processing.tile_quality`

The defaults preserve prior behavior (90 / 82 / 78 / 82 respectively), so upgrading does not silently change output quality.

### Bounded automatic rescue
The existing job-level RabbitMQ retries still run first. If the publisher explicitly enables **Automatic rescue after normal retries**, an unpublished edition may start one new full processing pipeline using the error-aware recovery profile after retry exhaustion.

Guardrails:

- default is **Off** to preserve beta.1.1 behavior;
- default maximum rescue passes is **1**;
- hard server limit is **2**;
- only recoverable renderer/image/OCR/timeout signatures trigger root-render rescue;
- page-asset finalization may trigger a rescue when required preview assets are incomplete;
- OCR-only warnings still do not block an otherwise healthy edition;
- published, archived and scheduled/live editions are not directly reprocessed;
- prior processing attempts remain intact.

### Manual Reprocess defaults
The beta.1.1 Reprocess drawer now respects `processing.recovery_manual_smart`. When enabled, recommended corrections are preselected for failed editions; one-off custom overrides still never mutate Publisher Control defaults.

### Processing history
Processing detail now surfaces the effective profile, including automatic recovery pass number, so operators can distinguish default, custom, recommended and auto-recovery attempts.

## Database

**No database migration is required.**

All new defaults are stored through the existing tenant-scoped `ep_settings` table. The latest required schema remains `database/upgrade-v2.0.0-beta.1.1.sql`.

## Worker restart

A Supervisor worker restart is required after deployment because `ProcessingPipeline.php` and `QueueService.php` changed. Restart render/assets/OCR/finalize workers after extracting the package, then verify `/admin/system/health`.

## Deployment

1. Take an on-demand Cloudways backup.
2. Confirm the application is already on `2.0.0-beta.1.1`.
3. Extract the beta.1.2 hotfix over the application root.
4. Do **not** run SQL.
5. Restart Supervisor workers.
6. Open `/admin/settings/processing` and verify current/default values.
7. Save once only if you want to change any processing/recovery policy.
8. Verify `/admin/system/health`, `/admin/processing`, and one non-production test edition.

## Recommended first test

Keep Automatic rescue **Off** initially and test a normal edition. Then, on a disposable/non-production edition, enable Automatic rescue with limit `1`, induce or use a known recoverable processing failure, and confirm:

1. normal RabbitMQ retries exhaust first;
2. one `auto_recovery` root pipeline appears;
3. previous failed jobs remain visible;
4. no second rescue occurs after the configured limit;
5. scheduled/published editions remain protected.
