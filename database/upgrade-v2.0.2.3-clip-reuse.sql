-- v2.0.2.3: associate reader-created clipping records with editorial marks.
-- Only run if mark_guid and ix_clip_mark are absent. The authenticated Upgrade Center
-- checks information_schema before applying these additive changes automatically.
-- Existing clips retain NULL mark_guid; no data is removed or rewritten.
ALTER TABLE ep_clips ADD COLUMN mark_guid BINARY(16) NULL AFTER page_guid;
ALTER TABLE ep_clips ADD INDEX ix_clip_mark (tenant_guid,page_guid,mark_guid,created_at);
