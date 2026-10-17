SET NAMES utf8mb4;

ALTER TABLE ep_identities
  ADD COLUMN IF NOT EXISTS identity_source VARCHAR(32) NOT NULL DEFAULT 'PLATFORM' AFTER display_name;

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
