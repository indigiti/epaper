-- Publisher-approved canonical clipping links, distinct from reader-created clips.
-- No user-created PRIVATE/PUBLIC clipping is promoted or referenced.
CREATE TABLE IF NOT EXISTS ep_official_clips (
 tenant_guid BINARY(16) NOT NULL,
 mark_guid BINARY(16) NOT NULL,
 page_guid BINARY(16) NOT NULL,
 clip_guid BINARY(16) NOT NULL,
 asset_guid BINARY(16) NOT NULL,
 asset_checksum CHAR(64) NULL,
 page_version BIGINT UNSIGNED NOT NULL,
 mark_updated_at DATETIME(6) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
 approved_by_user_guid BINARY(16) NOT NULL,
 approved_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(tenant_guid,mark_guid),
 KEY ix_official_page(tenant_guid,page_guid,status),
 KEY ix_official_clip(tenant_guid,clip_guid,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
