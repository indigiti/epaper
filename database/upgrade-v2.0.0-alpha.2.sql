-- EP-DI v2.0.0-alpha.2 — Runtime & Publishing Engine
-- Apply once after upgrade-v2.0.0-alpha.1.sql.

CREATE TABLE IF NOT EXISTS ep_navigation_scopes (
 nav_scope_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 scope_key VARCHAR(180) NOT NULL,
 name VARCHAR(190) NOT NULL,
 publication_guid BINARY(16) NULL,
 city_guid BINARY(16) NULL,
 language_guid BINARY(16) NULL,
 header_style VARCHAR(40) NOT NULL DEFAULT 'inherit',
 sticky_behavior VARCHAR(40) NOT NULL DEFAULT 'hide_down_reveal_up',
 status VARCHAR(40) NOT NULL DEFAULT 'DRAFT',
 draft_revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
 published_revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
 published_by_user_guid BINARY(16) NULL,
 published_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(nav_scope_guid),
 UNIQUE KEY uk_navigation_scope(tenant_guid,scope_key),
 KEY ix_navigation_scope_status(tenant_guid,status,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_navigation_items (
 nav_item_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 nav_scope_guid BINARY(16) NOT NULL,
 row_type VARCHAR(24) NOT NULL DEFAULT 'PRIMARY',
 label VARCHAR(160) NOT NULL,
 destination_type VARCHAR(40) NOT NULL DEFAULT 'CUSTOM_URL',
 custom_url VARCHAR(700) NULL,
 badge VARCHAR(40) NULL,
 visibility VARCHAR(24) NOT NULL DEFAULT 'ALL',
 open_mode VARCHAR(24) NOT NULL DEFAULT 'SAME_TAB',
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0,
 starts_at DATETIME(6) NULL,
 expires_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(nav_item_guid),
 KEY ix_navigation_item_row(tenant_guid,nav_scope_guid,row_type,sort_order),
 KEY ix_navigation_item_active(tenant_guid,nav_scope_guid,enabled,starts_at,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_navigation_versions (
 nav_version_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 nav_scope_guid BINARY(16) NOT NULL,
 version_number BIGINT UNSIGNED NOT NULL,
 compiled_json LONGTEXT NOT NULL,
 published_by_user_guid BINARY(16) NULL,
 published_at DATETIME(6) NOT NULL,
 PRIMARY KEY(nav_version_guid),
 UNIQUE KEY uk_navigation_version(tenant_guid,nav_scope_guid,version_number),
 KEY ix_navigation_version_recent(tenant_guid,nav_scope_guid,published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
