# ePaper SaaS v1.0.1.3 — Queue Dispatch Recovery

This is a no-schema hotfix for installations already on v1.0.1.2.

## Why
A job created before/while RabbitMQ was activated could remain `QUEUED` without a live outbox transport event. The outbox could also retain stale pending/processing rows from earlier processing attempts. The broker and workers could therefore be healthy while an edition stayed at 0%.

## Install
1. Back up the application/database.
2. Extract the hotfix into the outer Cloudways `public_html` and overwrite matching files.
3. Keep Webroot `public_html/public_html`.
4. No migration and no reinstall.
5. Restart the Outbox/Render/Assets/OCR/Finalize Supervisor jobs once so long-running workers load the new code.
6. Open `/admin/processing`. If an outbox backlog is shown, click **Repair queue dispatch** once.

## Expected result
The queued render event is reattached to the transactional outbox, the Outbox publisher sends it to `epaper.render`, and the pipeline begins Render → Assets/OCR → Finalize.
