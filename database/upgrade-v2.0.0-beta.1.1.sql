-- EP-DI v2.0.0-beta.1.1 — Editions Operations
-- Adds persistent publishing intent without changing existing edition status semantics.

ALTER TABLE ep_editions
  ADD COLUMN publish_mode VARCHAR(24) NOT NULL DEFAULT 'MANUAL' AFTER status,
  ADD COLUMN schedule_timezone VARCHAR(64) NULL AFTER publish_mode,
  ADD INDEX ix_edition_publish_schedule (tenant_guid,publish_mode,status,scheduled_at);

ALTER TABLE ep_processing_jobs
  ADD INDEX ix_processing_edition_type_created (tenant_guid,edition_guid,job_type,created_at);

-- Existing scheduled editions were created in the application timezone.
-- Keep their current scheduled_at value and mark the intent explicitly.
UPDATE ep_editions
SET publish_mode='SCHEDULED'
WHERE tenant_guid IS NOT NULL AND status='SCHEDULED' AND publish_mode='MANUAL';
