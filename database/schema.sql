SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS ep_tenants (
 tenant_guid BINARY(16) NOT NULL,
 organisation_guid BINARY(16) NULL,
 display_name VARCHAR(190) NOT NULL,
 primary_hostname VARCHAR(253) NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY (tenant_guid),
 KEY ix_tenant_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_settings (
 setting_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 setting_key VARCHAR(120) NOT NULL,
 setting_value LONGTEXT NULL,
 scope_type VARCHAR(32) NOT NULL DEFAULT 'TENANT',
 scope_guid BINARY(16) NULL,
 updated_by_user_guid BINARY(16) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(setting_guid),
 UNIQUE KEY uk_setting (tenant_guid,setting_key,scope_type,scope_guid),
 KEY ix_setting_tenant (tenant_guid,setting_key),
 KEY ix_setting_scope (tenant_guid,scope_type,scope_guid,setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_identities (
 platform_user_guid BINARY(16) NOT NULL,
 email VARCHAR(254) NULL,
 display_name VARCHAR(190) NULL,
 identity_source VARCHAR(32) NOT NULL DEFAULT 'PLATFORM',
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 last_seen_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(platform_user_guid),
 KEY ix_identity_email(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_subscribers (
 subscriber_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 platform_user_guid BINARY(16) NOT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 preferences_json LONGTEXT NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(subscriber_guid),
 UNIQUE KEY uk_tenant_subscriber(tenant_guid,platform_user_guid),
 KEY ix_subscriber_status(tenant_guid,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_local_mobile_accounts (
 account_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 user_guid BINARY(16) NOT NULL,
 phone_e164 VARCHAR(20) NOT NULL,
 phone_verified_at DATETIME(6) NULL,
 auth_provider VARCHAR(32) NOT NULL DEFAULT 'twilio_verify',
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 last_login_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(account_guid),
 UNIQUE KEY uk_local_mobile_phone(tenant_guid,phone_e164),
 UNIQUE KEY uk_local_mobile_user(tenant_guid,user_guid),
 KEY ix_local_mobile_status(tenant_guid,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_local_device_accounts (
 account_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 user_guid BINARY(16) NOT NULL,
 installation_hash CHAR(64) NOT NULL,
 request_ip_hash CHAR(64) NOT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 last_seen_at DATETIME(6) NULL,
 expires_at DATETIME(6) NOT NULL,
 merged_to_user_guid BINARY(16) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(account_guid),
 UNIQUE KEY uk_local_device_installation(tenant_guid,installation_hash),
 UNIQUE KEY uk_local_device_user(tenant_guid,user_guid),
 KEY ix_local_device_status(tenant_guid,status,expires_at),
 KEY ix_local_device_ip(tenant_guid,request_ip_hash,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_mobile_auth_challenges (
 challenge_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 phone_e164 VARCHAR(20) NOT NULL,
 channel VARCHAR(20) NOT NULL,
 provider VARCHAR(32) NOT NULL,
 provider_sid VARCHAR(80) NOT NULL,
 request_ip_hash CHAR(64) NOT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 expires_at DATETIME(6) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(challenge_guid),
 KEY ix_mobile_challenge_lookup(tenant_guid,status,expires_at),
 KEY ix_mobile_challenge_phone(tenant_guid,phone_e164,created_at),
 KEY ix_mobile_challenge_ip(tenant_guid,request_ip_hash,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_reader_devices (
 device_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 user_guid BINARY(16) NOT NULL,
 installation_hash CHAR(64) NOT NULL,
 credential_hash CHAR(64) NOT NULL,
 device_type VARCHAR(24) NOT NULL DEFAULT 'MOBILE_APP',
 device_label VARCHAR(120) NULL,
 platform VARCHAR(40) NULL,
 push_token_ciphertext LONGTEXT NULL,
 push_provider VARCHAR(24) NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'ACTIVE',
 trusted_at DATETIME(6) NULL,
 last_seen_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(device_guid),
 UNIQUE KEY uk_reader_device_installation(tenant_guid,installation_hash),
 KEY ix_reader_device_user(tenant_guid,user_guid,device_type,status),
 KEY ix_reader_device_seen(tenant_guid,status,last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_reader_sessions (
 reader_session_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 user_guid BINARY(16) NOT NULL,
 session_kind VARCHAR(24) NOT NULL,
 device_guid BINARY(16) NULL,
 session_hash CHAR(64) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'ACTIVE',
 last_seen_at DATETIME(6) NULL,
 expires_at DATETIME(6) NOT NULL,
 revoked_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(reader_session_guid),
 UNIQUE KEY uk_reader_session_hash(tenant_guid,session_hash),
 KEY ix_reader_session_user(tenant_guid,user_guid,session_kind,status),
 KEY ix_reader_session_expiry(tenant_guid,status,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_desktop_login_challenges (
 challenge_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 user_guid BINARY(16) NOT NULL,
 phone_e164 VARCHAR(20) NOT NULL,
 trusted_device_guid BINARY(16) NOT NULL,
 display_code CHAR(6) NOT NULL,
 browser_session_hash CHAR(64) NOT NULL,
 requested_ip_hash CHAR(64) NOT NULL,
 user_agent_summary VARCHAR(190) NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
 expires_at DATETIME(6) NOT NULL,
 decided_at DATETIME(6) NULL,
 consumed_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(challenge_guid),
 KEY ix_desktop_challenge_device(tenant_guid,trusted_device_guid,status,expires_at),
 KEY ix_desktop_challenge_user(tenant_guid,user_guid,status,created_at),
 KEY ix_desktop_challenge_expiry(tenant_guid,status,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_publications (
 publication_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 publication_code VARCHAR(40) NOT NULL,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(160) NOT NULL,
 logo_path VARCHAR(500) NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 is_default TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(publication_guid),
 UNIQUE KEY uk_publication_slug(tenant_guid,slug),
 UNIQUE KEY uk_publication_code(tenant_guid,publication_code),
 KEY ix_publication_status(tenant_guid,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_cities (
 city_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 name VARCHAR(120) NOT NULL,
 slug VARCHAR(120) NOT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(city_guid),
 UNIQUE KEY uk_city_slug(tenant_guid,slug),
 KEY ix_city_status(tenant_guid,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_languages (
 language_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 name VARCHAR(120) NOT NULL,
 code VARCHAR(16) NOT NULL,
 locale VARCHAR(32) NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(language_guid),
 UNIQUE KEY uk_language_code(tenant_guid,code),
 KEY ix_language_status(tenant_guid,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_sections (
 section_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 publication_guid BINARY(16) NULL,
 name VARCHAR(120) NOT NULL,
 slug VARCHAR(120) NOT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(section_guid),
 UNIQUE KEY uk_section_slug(tenant_guid,publication_guid,slug),
 KEY ix_section_sort(tenant_guid,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_editions (
 edition_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 publication_guid BINARY(16) NOT NULL,
 city_guid BINARY(16) NULL,
 language_guid BINARY(16) NULL,
 edition_date DATE NOT NULL,
 edition_type VARCHAR(32) NOT NULL DEFAULT 'DAILY',
 title VARCHAR(190) NULL,
 slug VARCHAR(220) NOT NULL,
 source_pdf_path VARCHAR(700) NULL,
 page_count INT NOT NULL DEFAULT 0,
 access_level VARCHAR(32) NOT NULL DEFAULT 'FREE',
 status VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
 publish_mode VARCHAR(24) NOT NULL DEFAULT 'MANUAL',
 schedule_timezone VARCHAR(64) NULL,
 scheduled_at DATETIME(6) NULL,
 published_at DATETIME(6) NULL,
 processing_completed_at DATETIME(6) NULL,
 version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 created_by_user_guid BINARY(16) NULL,
 updated_by_user_guid BINARY(16) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(edition_guid),
 UNIQUE KEY uk_edition_slug(tenant_guid,slug),
 KEY ix_edition_publication_date(tenant_guid,publication_guid,edition_date),
 KEY ix_edition_status_date(tenant_guid,status,edition_date),
 KEY ix_edition_publish_schedule(tenant_guid,publish_mode,status,scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_pages (
 page_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 edition_guid BINARY(16) NOT NULL,
 section_guid BINARY(16) NULL,
 page_number INT NOT NULL,
 width INT NOT NULL DEFAULT 0,
 height INT NOT NULL DEFAULT 0,
 label VARCHAR(120) NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'READY',
 version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(page_guid),
 UNIQUE KEY uk_page_number(tenant_guid,edition_guid,page_number),
 KEY ix_page_edition(tenant_guid,edition_guid,page_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_page_assets (
 asset_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 page_guid BINARY(16) NOT NULL,
 asset_type VARCHAR(40) NOT NULL,
 mime_type VARCHAR(100) NOT NULL,
 storage_path VARCHAR(700) NOT NULL,
 width INT NOT NULL DEFAULT 0,
 height INT NOT NULL DEFAULT 0,
 byte_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
 checksum_sha256 CHAR(64) NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(asset_guid),
 UNIQUE KEY uk_page_asset_type(tenant_guid,page_guid,asset_type),
 KEY ix_asset_page(tenant_guid,page_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_page_ocr (
 ocr_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 page_guid BINARY(16) NOT NULL,
 language VARCHAR(32) NULL,
 text_content LONGTEXT NOT NULL,
 hocr_content LONGTEXT NULL,
 confidence DECIMAL(5,2) NULL,
 engine VARCHAR(80) NULL DEFAULT 'tesseract',
 engine_version VARCHAR(80) NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'READY',
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(ocr_guid),
 UNIQUE KEY uk_page_ocr(tenant_guid,page_guid),
 FULLTEXT KEY ft_ocr_text(text_content),
 KEY ix_ocr_page(tenant_guid,page_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_ocr_blocks (
 block_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 page_guid BINARY(16) NOT NULL,
 block_order INT NOT NULL DEFAULT 0,
 block_type VARCHAR(32) NOT NULL DEFAULT 'TEXT',
 text_content TEXT NOT NULL,
 x1 INT NOT NULL,
 y1 INT NOT NULL,
 x2 INT NOT NULL,
 y2 INT NOT NULL,
 confidence DECIMAL(5,2) NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(block_guid),
 KEY ix_ocr_block_page(tenant_guid,page_guid,block_order),
 FULLTEXT KEY ft_block_text(text_content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_highlights (
 highlight_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 publication_guid BINARY(16) NULL,
 edition_guid BINARY(16) NULL,
 page_guid BINARY(16) NULL,
 title VARCHAR(255) NOT NULL,
 summary TEXT NULL,
 image_asset_guid BINARY(16) NULL,
 target_url VARCHAR(700) NULL,
 sort_order INT NOT NULL DEFAULT 0,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 starts_at DATETIME(6) NULL,
 ends_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(highlight_guid),
 KEY ix_highlight_active(tenant_guid,status,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_saved_articles (
 saved_article_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 platform_user_guid BINARY(16) NOT NULL,
 page_guid BINARY(16) NOT NULL,
 title VARCHAR(255) NULL,
 text_snapshot MEDIUMTEXT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(saved_article_guid),
 KEY ix_saved_user(tenant_guid,platform_user_guid,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_bookmarks (
 bookmark_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 platform_user_guid BINARY(16) NOT NULL,
 bookmark_type VARCHAR(32) NOT NULL,
 target_guid BINARY(16) NOT NULL,
 note TEXT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(bookmark_guid),
 UNIQUE KEY uk_bookmark(tenant_guid,platform_user_guid,bookmark_type,target_guid),
 KEY ix_bookmark_user(tenant_guid,platform_user_guid,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_clips (
 clip_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 platform_user_guid BINARY(16) NULL,
 page_guid BINARY(16) NOT NULL,
 mark_guid BINARY(16) NULL,
 clip_slug VARCHAR(64) NOT NULL,
 title VARCHAR(255) NULL,
 visibility VARCHAR(32) NOT NULL DEFAULT 'UNLISTED',
 x DECIMAL(10,7) NOT NULL,
 y DECIMAL(10,7) NOT NULL,
 width DECIMAL(10,7) NOT NULL,
 height DECIMAL(10,7) NOT NULL,
 storage_path VARCHAR(700) NULL,
 expires_at DATETIME(6) NULL,
 view_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(clip_guid),
 UNIQUE KEY uk_clip_slug(tenant_guid,clip_slug),
 KEY ix_clip_user(tenant_guid,platform_user_guid,created_at),
 KEY ix_clip_mark(tenant_guid,page_guid,mark_guid,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS ep_history (
 history_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 platform_user_guid BINARY(16) NOT NULL,
 edition_guid BINARY(16) NOT NULL,
 page_guid BINARY(16) NOT NULL,
 page_number INT NOT NULL,
 duration_seconds INT NOT NULL DEFAULT 0,
 last_viewed_at DATETIME(6) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(history_guid),
 UNIQUE KEY uk_history_current(tenant_guid,platform_user_guid,edition_guid),
 KEY ix_history_user(tenant_guid,platform_user_guid,last_viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_offline_devices (
 device_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 platform_user_guid BINARY(16) NOT NULL,
 label VARCHAR(120) NOT NULL,
 public_key_spki TEXT NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'ACTIVE',
 last_seen_at DATETIME(6) NULL,
 revoked_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(device_guid),
 UNIQUE KEY uk_offline_device_user(tenant_guid,platform_user_guid,device_guid),
 KEY ix_offline_device_user_status(tenant_guid,platform_user_guid,status,last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_offline_licenses (
 license_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 platform_user_guid BINARY(16) NOT NULL,
 device_guid BINARY(16) NOT NULL,
 edition_guid BINARY(16) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'ISSUED',
 wrapped_key_json LONGTEXT NOT NULL,
 sealed_cek LONGTEXT NOT NULL,
 manifest_json LONGTEXT NOT NULL,
 manifest_hash CHAR(64) NOT NULL,
 byte_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
 issued_at DATETIME(6) NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 revoked_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(license_guid),
 KEY ix_offline_license_user(tenant_guid,platform_user_guid,status,expires_at),
 KEY ix_offline_license_device(tenant_guid,platform_user_guid,device_guid,status,expires_at),
 KEY ix_offline_license_edition(tenant_guid,platform_user_guid,edition_guid,status,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_offline_items (
 offline_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 platform_user_guid BINARY(16) NOT NULL,
 edition_guid BINARY(16) NOT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'READY',
 byte_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
 device_guid BINARY(16) NULL,
 license_guid BINARY(16) NULL,
 expires_at DATETIME(6) NULL,
 page_count INT UNSIGNED NOT NULL DEFAULT 0,
 downloaded_pages INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(offline_guid),
 UNIQUE KEY uk_offline_edition(tenant_guid,platform_user_guid,device_guid,edition_guid),
 KEY ix_offline_user_status_expiry(tenant_guid,platform_user_guid,status,expires_at),
 KEY ix_offline_license(tenant_guid,platform_user_guid,license_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_reader_preferences (
 preference_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 platform_user_guid BINARY(16) NOT NULL,
 preferences_json LONGTEXT NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(preference_guid),
 UNIQUE KEY uk_reader_pref(tenant_guid,platform_user_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_notifications (
 notification_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 platform_user_guid BINARY(16) NULL,
 category VARCHAR(60) NOT NULL,
 title VARCHAR(255) NOT NULL,
 body TEXT NULL,
 target_url VARCHAR(700) NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'UNREAD',
 created_at DATETIME(6) NOT NULL,
 read_at DATETIME(6) NULL,
 PRIMARY KEY(notification_guid),
 KEY ix_notification_user(tenant_guid,platform_user_guid,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_processing_jobs (
 job_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 edition_guid BINARY(16) NULL,
 job_type VARCHAR(64) NOT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'QUEUED',
 progress DECIMAL(5,2) NOT NULL DEFAULT 0,
 attempt_count INT NOT NULL DEFAULT 0,
 available_at DATETIME(6) NULL,
 locked_at DATETIME(6) NULL,
 worker_id VARCHAR(190) NULL,
 error_message TEXT NULL,
 payload_json LONGTEXT NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 completed_at DATETIME(6) NULL,
 PRIMARY KEY(job_guid),
 KEY ix_processing_queue(tenant_guid,status,available_at,created_at),
 KEY ix_processing_edition(tenant_guid,edition_guid,status),
 KEY ix_processing_edition_type_created(tenant_guid,edition_guid,job_type,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_processing_steps (
 step_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 job_guid BINARY(16) NOT NULL,
 page_guid BINARY(16) NULL,
 step_key VARCHAR(64) NOT NULL,
 status VARCHAR(32) NOT NULL,
 duration_ms INT NULL,
 message TEXT NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(step_guid),
 KEY ix_step_job(tenant_guid,job_guid,step_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_analytics_events (
 analytics_event_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 platform_user_guid BINARY(16) NULL,
 edition_guid BINARY(16) NULL,
 page_guid BINARY(16) NULL,
 event_name VARCHAR(80) NOT NULL,
 request_guid BINARY(16) NULL,
 metadata_json LONGTEXT NULL,
 occurred_at DATETIME(6) NOT NULL,
 PRIMARY KEY(analytics_event_guid),
 KEY ix_analytics_event(tenant_guid,event_name,occurred_at),
 KEY ix_analytics_page(tenant_guid,page_guid,occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_audit_events (
 audit_event_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NULL,
 occurred_at DATETIME(6) NOT NULL,
 actor_type VARCHAR(32) NOT NULL,
 actor_user_guid BINARY(16) NULL,
 membership_guid BINARY(16) NULL,
 action VARCHAR(120) NOT NULL,
 resource_type VARCHAR(80) NOT NULL,
 resource_guid BINARY(16) NULL,
 result VARCHAR(32) NOT NULL,
 request_guid BINARY(16) NULL,
 correlation_guid BINARY(16) NULL,
 metadata_json LONGTEXT NULL,
 PRIMARY KEY(audit_event_guid),
 KEY ix_audit_tenant(tenant_guid,occurred_at),
 KEY ix_audit_request(request_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_error_events (
 error_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NULL,
 occurred_at DATETIME(6) NOT NULL,
 request_guid BINARY(16) NULL,
 correlation_guid BINARY(16) NULL,
 user_guid BINARY(16) NULL,
 level VARCHAR(16) NOT NULL,
 exception_class VARCHAR(255) NULL,
 message VARCHAR(1000) NOT NULL,
 file VARCHAR(700) NULL,
 line INT NULL,
 fingerprint CHAR(64) NULL,
 metadata_json LONGTEXT NULL,
 PRIMARY KEY(error_guid),
 KEY ix_error_tenant(tenant_guid,occurred_at),
 KEY ix_error_request(request_guid),
 KEY ix_error_fingerprint(tenant_guid,fingerprint,occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_outbox_events (
 outbox_event_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NULL,
 event_type VARCHAR(120) NOT NULL,
 payload_json LONGTEXT NOT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
 attempt_count INT NOT NULL DEFAULT 0,
 available_at DATETIME(6) NOT NULL,
 locked_at DATETIME(6) NULL,
 last_error TEXT NULL,
 created_at DATETIME(6) NOT NULL,
 processed_at DATETIME(6) NULL,
 PRIMARY KEY(outbox_event_guid),
 KEY ix_outbox(status,available_at,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_system_state (
 state_key VARCHAR(120) NOT NULL,
 state_value LONGTEXT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(state_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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


SET FOREIGN_KEY_CHECKS=1;
-- EP-DI v2.0.0-rc.1 — Semantic News / SEO / Trust Infrastructure

CREATE TABLE IF NOT EXISTS ep_authors (
 author_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 job_title VARCHAR(190) NULL,
 beat VARCHAR(190) NULL,
 bio TEXT NULL,
 photo_url VARCHAR(700) NULL,
 public_email VARCHAR(190) NULL,
 same_as_json LONGTEXT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(author_guid),
 UNIQUE KEY uk_author_slug(tenant_guid,slug),
 KEY ix_author_status(tenant_guid,status,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_topics (
 topic_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 publication_guid BINARY(16) NULL,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 description TEXT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(topic_guid),
 UNIQUE KEY uk_topic_slug(tenant_guid,slug),
 KEY ix_topic_status(tenant_guid,status,name),
 KEY ix_topic_publication(tenant_guid,publication_guid,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_entities (
 entity_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 entity_type VARCHAR(32) NOT NULL DEFAULT 'OTHER',
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 description TEXT NULL,
 canonical_url VARCHAR(700) NULL,
 same_as_json LONGTEXT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(entity_guid),
 UNIQUE KEY uk_entity_slug(tenant_guid,slug),
 KEY ix_entity_type_status(tenant_guid,entity_type,status,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_articles (
 article_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 publication_guid BINARY(16) NOT NULL,
 city_guid BINARY(16) NULL,
 language_guid BINARY(16) NULL,
 section_guid BINARY(16) NULL,
 edition_guid BINARY(16) NULL,
 page_guid BINARY(16) NULL,
 title VARCHAR(250) NOT NULL,
 slug VARCHAR(220) NOT NULL,
 deck TEXT NULL,
 body LONGTEXT NOT NULL,
 primary_image_url VARCHAR(700) NULL,
 translation_key VARCHAR(190) NULL,
 source_notes TEXT NULL,
 correction_note TEXT NULL,
 ai_assisted TINYINT(1) NOT NULL DEFAULT 0,
 ai_disclosure VARCHAR(700) NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
 published_at DATETIME(6) NULL,
 version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 created_by_user_guid BINARY(16) NULL,
 updated_by_user_guid BINARY(16) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(article_guid),
 UNIQUE KEY uk_article_slug(tenant_guid,slug),
 KEY ix_article_status_published(tenant_guid,status,published_at),
 KEY ix_article_publication_status(tenant_guid,publication_guid,status,published_at),
 KEY ix_article_edition_page(tenant_guid,edition_guid,page_guid),
 KEY ix_article_translation(tenant_guid,translation_key,status),
 FULLTEXT KEY ft_article_content(title,deck,body)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_article_authors (
 tenant_guid BINARY(16) NOT NULL,
 article_guid BINARY(16) NOT NULL,
 author_guid BINARY(16) NOT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 PRIMARY KEY(article_guid,author_guid),
 KEY ix_article_author_lookup(tenant_guid,author_guid,article_guid),
 KEY ix_article_author_order(tenant_guid,article_guid,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_article_topics (
 tenant_guid BINARY(16) NOT NULL,
 article_guid BINARY(16) NOT NULL,
 topic_guid BINARY(16) NOT NULL,
 PRIMARY KEY(article_guid,topic_guid),
 KEY ix_article_topic_lookup(tenant_guid,topic_guid,article_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_article_entities (
 tenant_guid BINARY(16) NOT NULL,
 article_guid BINARY(16) NOT NULL,
 entity_guid BINARY(16) NOT NULL,
 PRIMARY KEY(article_guid,entity_guid),
 KEY ix_article_entity_lookup(tenant_guid,entity_guid,article_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
