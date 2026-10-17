-- EP-DI v2.0.1.7 Flexible Reader Identity
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
