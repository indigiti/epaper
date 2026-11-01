-- Clip Marking: only normalized article outlines are stored; images are cropped on reader request.
CREATE TABLE IF NOT EXISTS ep_clip_marks (
 mark_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 page_guid BINARY(16) NOT NULL,
 platform_user_guid BINARY(16) NOT NULL,
 title VARCHAR(180) NOT NULL,
 x DECIMAL(10,7) NOT NULL,
 y DECIMAL(10,7) NOT NULL,
 width DECIMAL(10,7) NOT NULL,
 height DECIMAL(10,7) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(mark_guid),
 KEY ix_mark_page(tenant_guid,page_guid,status,created_at),
 KEY ix_mark_actor(tenant_guid,platform_user_guid,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
