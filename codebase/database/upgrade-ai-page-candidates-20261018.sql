-- OPTIONAL / staging only. Apply only after taking an isolated backup.
-- Candidate rows are strictly tenant-scoped and never publish articles/clips.
CREATE TABLE IF NOT EXISTS ep_ai_page_candidates (
  candidate_guid BINARY(16) NOT NULL,
  tenant_guid BINARY(16) NOT NULL,
  edition_guid BINARY(16) NOT NULL,
  page_guid BINARY(16) NOT NULL,
  source_sha256 CHAR(64) NOT NULL,
  candidate_json LONGTEXT NOT NULL,
  editor_json LONGTEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
  version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  reviewed_by_user_guid BINARY(16) NULL,
  reviewed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (candidate_guid),
  UNIQUE KEY uk_ai_candidate_tenant_page (tenant_guid,page_guid),
  KEY ix_ai_candidate_queue (tenant_guid,status,updated_at),
  KEY ix_ai_candidate_edition (tenant_guid,edition_guid,page_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
