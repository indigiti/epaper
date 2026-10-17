-- EP-DI v2.0.0-beta.2 — Reader OS + Encrypted PWA Offline Vault

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

ALTER TABLE ep_offline_items
  ADD COLUMN device_guid BINARY(16) NULL AFTER byte_size,
  ADD COLUMN license_guid BINARY(16) NULL AFTER device_guid,
  ADD COLUMN expires_at DATETIME(6) NULL AFTER license_guid,
  ADD COLUMN page_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER expires_at,
  ADD COLUMN downloaded_pages INT UNSIGNED NOT NULL DEFAULT 0 AFTER page_count;

ALTER TABLE ep_offline_items DROP INDEX uk_offline_edition;

ALTER TABLE ep_offline_items
  ADD UNIQUE KEY uk_offline_edition(tenant_guid,platform_user_guid,device_guid,edition_guid),
  ADD KEY ix_offline_user_status_expiry(tenant_guid,platform_user_guid,status,expires_at),
  ADD KEY ix_offline_license(tenant_guid,platform_user_guid,license_guid);
