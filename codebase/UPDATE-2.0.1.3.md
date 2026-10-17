# EP-DI v2.0.1.3 — Edition Recovery & Dashboard Cleanup

Upgrade from the supplied v2.0.1.2 cumulative installation.

1. Back up application files and MariaDB. Record the current Supervisor worker groups.
2. Temporarily pause publishing/admin writes and the scheduler. Stop the EP-DI processing workers and outbox publisher using your existing Supervisor controls. Confirm old workers and their PDF/OCR subprocesses have stopped before proceeding.
3. Extract this ZIP into the existing application root (the folder containing `app`, `bin`, `config`, `public_html`, and `VERSION`). Preserve your `config/config.php`, `config/runtime.php`, storage PDFs/media, secrets, and server configuration. The ZIP includes no live configuration or customer media.
4. No new SQL migration is required for an aligned v2.0.1.2 installation. Do not run installer/reset or database repair just to apply this update.
5. Reload PHP/clear OPcache through your hosting controls, then start all existing EP-DI Supervisor groups and resume the scheduler. Worker commands and queue topology are unchanged. A full worker restart is REQUIRED before using recovery or Trash.
6. Hard refresh the admin. Verify VERSION 2.0.1.3 and /admin/system/health. Check /admin, /admin/editions and a known public edition.

## Edition controls

- **Recover** redispatches existing unowned jobs immediately. Busy workers keep their job locks; recovery never takes over a live job. RabbitMQ recovery writes a durable outbox event, so the outbox publisher and workers must be running.
- **Cancel** records a stop request. Queued/unowned work stops immediately; live work stops at a checkpoint. Refresh to see completion. If a worker died after a stop request, use Cancel again to settle the abandoned jobs.
- **Reprocess** starts a new complete pipeline only when previous work is no longer active. It retains job/audit history, but rebuilds derived page assets for this unpublished edition. A partial render that already dispatched page jobs requires Cancel, then Reprocess.
- **Move to Trash** removes an unpublished edition from the default administration view, retains its PDF/media, and cooperatively cancels processing. OWNER/ADMIN/PUBLISHER_ADMIN/EDITOR can trash; ordinary OPERATOR cannot. Select **Trashed** in the status filter, then Restore. Restore returns to Draft, clears publishing intent, and requires processing/review before publishing.
- Published editions must first be archived. Scheduled editions must first have their schedule cancelled (or be archived). Trash is reversible removal, not permanent file/database deletion.
- These are web-triggered recovery controls. Full PDF/OCR processing remains worker-driven; this release does not implement browser-based incremental processing.

## Dashboard

Clickable edition counters, up to three primary alerts, expandable additional notices, recent editions, upcoming publications and compact shortcuts. Editions use 25-record pagination and status/date/search filters. Processing counts refer to editions on dashboard cards; diagnostic job counts remain job counts. Historical job failures no longer inflate the current edition-attention count.

## Deployment smoke checks

On a disposable draft: queue work; verify a second enqueue is blocked; Recover; Cancel; move to Trash; filter Trashed; Restore; Reprocess. Verify the public reader, media, login/logout, subscriber entitlement and offline workflows using existing known-good accounts/editions. For RabbitMQ, confirm new worker versions, outbox dispatch and all four lanes. Do not use a live published edition for destructive processing tests.

See RELEASE-CHECK-2.0.1.3.txt and ROLLBACK-2.0.1.3.md for evidence and limits.
