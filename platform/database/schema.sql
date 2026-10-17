SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS schema_migrations (
  migration_key VARCHAR(128) NOT NULL,
  applied_at DATETIME(6) NOT NULL,
  PRIMARY KEY(migration_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS platform_admins (
  admin_guid BINARY(16) NOT NULL,
  email VARCHAR(190) NOT NULL,
  display_name VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  mfa_secret_enc VARBINARY(512) NULL,
  mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
  role_key VARCHAR(64) NOT NULL DEFAULT 'SUPER_ADMIN',
  status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  last_login_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(admin_guid), UNIQUE KEY uk_platform_admin_email(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organisations (
  organisation_guid BINARY(16) NOT NULL,
  organisation_code VARCHAR(64) NOT NULL,
  legal_name VARCHAR(255) NOT NULL,
  display_name VARCHAR(255) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  metadata JSON NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(organisation_guid), UNIQUE KEY uk_org_code(organisation_code), KEY ix_org_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_users (
  user_guid BINARY(16) NOT NULL,
  email VARCHAR(190) NOT NULL,
  display_name VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  mfa_secret_enc VARBINARY(512) NULL,
  mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  email_verified_at DATETIME(6) NULL,
  last_login_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(user_guid), UNIQUE KEY uk_user_email(email), KEY ix_user_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS identity_sessions (
  session_guid BINARY(16) NOT NULL,
  user_guid BINARY(16) NULL,
  admin_guid BINARY(16) NULL,
  session_hash BINARY(32) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  last_seen_at DATETIME(6) NOT NULL,
  expires_at DATETIME(6) NOT NULL,
  revoked_at DATETIME(6) NULL,
  user_agent VARCHAR(255) NULL,
  ip_address VARCHAR(64) NULL,
  PRIMARY KEY(session_guid),
  UNIQUE KEY uk_identity_session_hash(session_hash),
  KEY ix_identity_user(user_guid,revoked_at,expires_at),
  KEY ix_identity_admin(admin_guid,revoked_at,expires_at),
  CONSTRAINT fk_identity_user FOREIGN KEY(user_guid) REFERENCES platform_users(user_guid),
  CONSTRAINT fk_identity_admin FOREIGN KEY(admin_guid) REFERENCES platform_admins(admin_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS memberships (
  membership_guid BINARY(16) NOT NULL,
  organisation_guid BINARY(16) NOT NULL,
  user_guid BINARY(16) NOT NULL,
  role_key VARCHAR(64) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(membership_guid),
  UNIQUE KEY uk_membership_org_user(organisation_guid,user_guid),
  KEY ix_membership_user(user_guid,status),
  CONSTRAINT fk_membership_org FOREIGN KEY(organisation_guid) REFERENCES organisations(organisation_guid),
  CONSTRAINT fk_membership_user FOREIGN KEY(user_guid) REFERENCES platform_users(user_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
  role_guid BINARY(16) NOT NULL,
  scope VARCHAR(32) NOT NULL,
  product_guid BINARY(16) NULL,
  role_key VARCHAR(64) NOT NULL,
  name VARCHAR(128) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY(role_guid), UNIQUE KEY uk_role_scope_key(scope,role_key,product_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  permission_guid BINARY(16) NOT NULL,
  permission_key VARCHAR(128) NOT NULL,
  description VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY(permission_guid), UNIQUE KEY uk_permission_key(permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_guid BINARY(16) NOT NULL,
  permission_guid BINARY(16) NOT NULL,
  PRIMARY KEY(role_guid,permission_guid),
  CONSTRAINT fk_rp_role FOREIGN KEY(role_guid) REFERENCES roles(role_guid),
  CONSTRAINT fk_rp_permission FOREIGN KEY(permission_guid) REFERENCES permissions(permission_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  product_guid BINARY(16) NOT NULL,
  product_key VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  description TEXT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(product_guid), UNIQUE KEY uk_product_key(product_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS applications (
  application_guid BINARY(16) NOT NULL,
  product_guid BINARY(16) NOT NULL,
  application_key VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  base_url VARCHAR(255) NOT NULL,
  admin_url VARCHAR(255) NULL,
  health_url VARCHAR(255) NULL,
  callback_url VARCHAR(255) NULL,
  oauth_client_id VARCHAR(96) NULL,
  integration_mode VARCHAR(32) NOT NULL DEFAULT 'REMOTE_API',
  current_version VARCHAR(32) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(application_guid),
  UNIQUE KEY uk_app_key(application_key), UNIQUE KEY uk_oauth_client(oauth_client_id),
  KEY ix_app_product(product_guid,status),
  CONSTRAINT fk_app_product FOREIGN KEY(product_guid) REFERENCES products(product_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_redirect_uris (
  redirect_guid BINARY(16) NOT NULL,
  application_guid BINARY(16) NOT NULL,
  redirect_uri VARCHAR(255) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY(redirect_guid),
  UNIQUE KEY uk_app_redirect_uri(application_guid,redirect_uri),
  CONSTRAINT fk_redirect_app FOREIGN KEY(application_guid) REFERENCES applications(application_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_tenants (
  product_tenant_guid BINARY(16) NOT NULL,
  organisation_guid BINARY(16) NOT NULL,
  product_guid BINARY(16) NOT NULL,
  external_tenant_reference VARCHAR(190) NULL,
  display_name VARCHAR(190) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(product_tenant_guid),
  UNIQUE KEY uk_product_tenant_org(organisation_guid,product_guid),
  KEY ix_pt_product(product_guid,status),
  CONSTRAINT fk_pt_org FOREIGN KEY(organisation_guid) REFERENCES organisations(organisation_guid),
  CONSTRAINT fk_pt_product FOREIGN KEY(product_guid) REFERENCES products(product_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS domains (
  domain_guid BINARY(16) NOT NULL,
  organisation_guid BINARY(16) NOT NULL,
  product_guid BINARY(16) NOT NULL,
  application_guid BINARY(16) NOT NULL,
  product_tenant_guid BINARY(16) NOT NULL,
  hostname VARCHAR(253) NOT NULL,
  normalized_hostname VARCHAR(253) NOT NULL,
  domain_type VARCHAR(32) NOT NULL DEFAULT 'CUSTOMER_CUSTOM',
  status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  default_resource_type VARCHAR(64) NULL,
  default_resource_guid BINARY(16) NULL,
  verification_token VARCHAR(128) NOT NULL,
  dns_metadata JSON NULL,
  edge_metadata JSON NULL,
  verified_at DATETIME(6) NULL,
  ssl_ready_at DATETIME(6) NULL,
  activated_at DATETIME(6) NULL,
  last_checked_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(domain_guid),
  UNIQUE KEY uk_domain_hostname(normalized_hostname),
  KEY ix_domain_tenant(product_tenant_guid,status),
  CONSTRAINT fk_domain_org FOREIGN KEY(organisation_guid) REFERENCES organisations(organisation_guid),
  CONSTRAINT fk_domain_product FOREIGN KEY(product_guid) REFERENCES products(product_guid),
  CONSTRAINT fk_domain_app FOREIGN KEY(application_guid) REFERENCES applications(application_guid),
  CONSTRAINT fk_domain_tenant FOREIGN KEY(product_tenant_guid) REFERENCES product_tenants(product_tenant_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_accounts (
  service_account_guid BINARY(16) NOT NULL,
  product_guid BINARY(16) NULL,
  name VARCHAR(190) NOT NULL,
  token_hash BINARY(32) NOT NULL,
  scopes JSON NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  last_used_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  rotated_at DATETIME(6) NULL,
  PRIMARY KEY(service_account_guid), UNIQUE KEY uk_service_token(token_hash),
  CONSTRAINT fk_service_product FOREIGN KEY(product_guid) REFERENCES products(product_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS features (
  feature_guid BINARY(16) NOT NULL,
  product_guid BINARY(16) NULL,
  feature_key VARCHAR(128) NOT NULL,
  name VARCHAR(190) NOT NULL,
  feature_type VARCHAR(32) NOT NULL DEFAULT 'BOOLEAN',
  unit VARCHAR(64) NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY(feature_guid), UNIQUE KEY uk_feature_key(feature_key),
  KEY ix_feature_product(product_guid),
  CONSTRAINT fk_feature_product FOREIGN KEY(product_guid) REFERENCES products(product_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plans (
  plan_guid BINARY(16) NOT NULL,
  product_guid BINARY(16) NOT NULL,
  plan_key VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(plan_guid), UNIQUE KEY uk_plan_product_key(product_guid,plan_key),
  CONSTRAINT fk_plan_product FOREIGN KEY(product_guid) REFERENCES products(product_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prices (
  price_guid BINARY(16) NOT NULL,
  plan_guid BINARY(16) NOT NULL,
  currency CHAR(3) NOT NULL,
  amount_minor BIGINT NOT NULL,
  billing_interval VARCHAR(32) NOT NULL,
  interval_count INT NOT NULL DEFAULT 1,
  status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY(price_guid), KEY ix_price_plan(plan_guid,status),
  CONSTRAINT fk_price_plan FOREIGN KEY(plan_guid) REFERENCES plans(plan_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_features (
  plan_guid BINARY(16) NOT NULL,
  feature_guid BINARY(16) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  limit_value DECIMAL(20,4) NULL,
  config_json JSON NULL,
  PRIMARY KEY(plan_guid,feature_guid),
  CONSTRAINT fk_pf_plan FOREIGN KEY(plan_guid) REFERENCES plans(plan_guid),
  CONSTRAINT fk_pf_feature FOREIGN KEY(feature_guid) REFERENCES features(feature_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
  subscription_guid BINARY(16) NOT NULL,
  product_tenant_guid BINARY(16) NOT NULL,
  plan_guid BINARY(16) NOT NULL,
  price_guid BINARY(16) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  billing_state VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  service_access_state VARCHAR(32) NOT NULL DEFAULT 'FULL',
  current_period_start DATETIME(6) NOT NULL,
  current_period_end DATETIME(6) NOT NULL,
  cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
  version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(subscription_guid), KEY ix_sub_tenant(product_tenant_guid,status),
  CONSTRAINT fk_sub_tenant FOREIGN KEY(product_tenant_guid) REFERENCES product_tenants(product_tenant_guid),
  CONSTRAINT fk_sub_plan FOREIGN KEY(plan_guid) REFERENCES plans(plan_guid),
  CONSTRAINT fk_sub_price FOREIGN KEY(price_guid) REFERENCES prices(price_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entitlement_grants (
  grant_guid BINARY(16) NOT NULL,
  product_tenant_guid BINARY(16) NOT NULL,
  feature_guid BINARY(16) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  limit_value DECIMAL(20,4) NULL,
  config_json JSON NULL,
  reason VARCHAR(255) NULL,
  expires_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY(grant_guid), KEY ix_grant_tenant(product_tenant_guid,expires_at),
  CONSTRAINT fk_grant_tenant FOREIGN KEY(product_tenant_guid) REFERENCES product_tenants(product_tenant_guid),
  CONSTRAINT fk_grant_feature FOREIGN KEY(feature_guid) REFERENCES features(feature_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_meters (
  usage_guid BINARY(16) NOT NULL,
  product_tenant_guid BINARY(16) NOT NULL,
  feature_guid BINARY(16) NOT NULL,
  period_key VARCHAR(32) NOT NULL,
  used_value DECIMAL(20,4) NOT NULL DEFAULT 0,
  reserved_value DECIMAL(20,4) NOT NULL DEFAULT 0,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(usage_guid), UNIQUE KEY uk_usage_period(product_tenant_guid,feature_guid,period_key),
  CONSTRAINT fk_usage_tenant FOREIGN KEY(product_tenant_guid) REFERENCES product_tenants(product_tenant_guid),
  CONSTRAINT fk_usage_feature FOREIGN KEY(feature_guid) REFERENCES features(feature_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
  invoice_guid BINARY(16) NOT NULL,
  product_tenant_guid BINARY(16) NOT NULL,
  invoice_number VARCHAR(64) NOT NULL,
  currency CHAR(3) NOT NULL,
  subtotal_minor BIGINT NOT NULL DEFAULT 0,
  tax_minor BIGINT NOT NULL DEFAULT 0,
  total_minor BIGINT NOT NULL DEFAULT 0,
  status VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
  due_at DATETIME(6) NULL,
  paid_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(invoice_guid), UNIQUE KEY uk_invoice_number(invoice_number), KEY ix_invoice_tenant(product_tenant_guid,status),
  CONSTRAINT fk_invoice_tenant FOREIGN KEY(product_tenant_guid) REFERENCES product_tenants(product_tenant_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
  invoice_item_guid BINARY(16) NOT NULL,
  invoice_guid BINARY(16) NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(12,4) NOT NULL DEFAULT 1,
  unit_amount_minor BIGINT NOT NULL,
  line_total_minor BIGINT NOT NULL,
  metadata JSON NULL,
  PRIMARY KEY(invoice_item_guid), KEY ix_invoice_item(invoice_guid),
  CONSTRAINT fk_invoice_item_invoice FOREIGN KEY(invoice_guid) REFERENCES invoices(invoice_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  payment_guid BINARY(16) NOT NULL,
  product_tenant_guid BINARY(16) NOT NULL,
  invoice_guid BINARY(16) NULL,
  provider VARCHAR(32) NOT NULL,
  amount_minor BIGINT NOT NULL,
  currency CHAR(3) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'CREATED',
  provider_reference VARCHAR(190) NULL,
  idempotency_key VARCHAR(190) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(payment_guid), UNIQUE KEY uk_payment_idem(idempotency_key), KEY ix_payment_tenant(product_tenant_guid,status),
  CONSTRAINT fk_payment_tenant FOREIGN KEY(product_tenant_guid) REFERENCES product_tenants(product_tenant_guid),
  CONSTRAINT fk_payment_invoice FOREIGN KEY(invoice_guid) REFERENCES invoices(invoice_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_attempts (
  attempt_guid BINARY(16) NOT NULL,
  payment_guid BINARY(16) NOT NULL,
  provider_reference VARCHAR(190) NULL,
  status VARCHAR(32) NOT NULL,
  request_metadata JSON NULL,
  response_metadata JSON NULL,
  failure_code VARCHAR(96) NULL,
  created_at DATETIME(6) NOT NULL,
  completed_at DATETIME(6) NULL,
  PRIMARY KEY(attempt_guid), KEY ix_attempt_payment(payment_guid,created_at),
  CONSTRAINT fk_attempt_payment FOREIGN KEY(payment_guid) REFERENCES payments(payment_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS refunds (
  refund_guid BINARY(16) NOT NULL,
  payment_guid BINARY(16) NOT NULL,
  amount_minor BIGINT NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'REQUESTED',
  provider_reference VARCHAR(190) NULL,
  reason VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(refund_guid), KEY ix_refund_payment(payment_guid,status),
  CONSTRAINT fk_refund_payment FOREIGN KEY(payment_guid) REFERENCES payments(payment_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_events (
  webhook_event_guid BINARY(16) NOT NULL,
  provider VARCHAR(32) NOT NULL,
  provider_event_reference VARCHAR(190) NULL,
  verification_status VARCHAR(32) NOT NULL DEFAULT 'UNVERIFIED',
  processing_status VARCHAR(32) NOT NULL DEFAULT 'RECEIVED',
  headers_json JSON NULL,
  payload MEDIUMTEXT NOT NULL,
  received_at DATETIME(6) NOT NULL,
  processed_at DATETIME(6) NULL,
  error_message VARCHAR(1000) NULL,
  PRIMARY KEY(webhook_event_guid), UNIQUE KEY uk_webhook_provider_ref(provider,provider_event_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_codes (
  auth_code_guid BINARY(16) NOT NULL,
  code_hash BINARY(32) NOT NULL,
  user_guid BINARY(16) NOT NULL,
  application_guid BINARY(16) NOT NULL,
  redirect_uri VARCHAR(255) NOT NULL,
  code_challenge VARCHAR(128) NOT NULL,
  state VARCHAR(255) NULL,
  expires_at DATETIME(6) NOT NULL,
  used_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY(auth_code_guid), UNIQUE KEY uk_auth_code_hash(code_hash), KEY ix_auth_exp(expires_at,used_at),
  CONSTRAINT fk_auth_user FOREIGN KEY(user_guid) REFERENCES platform_users(user_guid),
  CONSTRAINT fk_auth_app FOREIGN KEY(application_guid) REFERENCES applications(application_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_events (
  audit_event_guid BINARY(16) NOT NULL,
  tenant_guid BINARY(16) NULL,
  occurred_at DATETIME(6) NOT NULL,
  actor_type VARCHAR(32) NOT NULL,
  actor_user_guid BINARY(16) NULL,
  membership_guid BINARY(16) NULL,
  impersonator_user_guid BINARY(16) NULL,
  action VARCHAR(128) NOT NULL,
  resource_type VARCHAR(96) NOT NULL,
  resource_guid BINARY(16) NULL,
  result VARCHAR(32) NOT NULL,
  request_guid BINARY(16) NULL,
  trace_id VARCHAR(64) NULL,
  correlation_guid BINARY(16) NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  before_data JSON NULL,
  after_data JSON NULL,
  metadata JSON NULL,
  PRIMARY KEY(audit_event_guid), KEY ix_audit_time(occurred_at), KEY ix_audit_tenant(tenant_guid,occurred_at), KEY ix_audit_request(request_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS error_events (
  error_guid BINARY(16) NOT NULL,
  occurred_at DATETIME(6) NOT NULL,
  request_guid BINARY(16) NULL,
  tenant_guid BINARY(16) NULL,
  domain_guid BINARY(16) NULL,
  user_guid BINARY(16) NULL,
  level VARCHAR(32) NOT NULL,
  exception_class VARCHAR(255) NULL,
  message VARCHAR(1000) NOT NULL,
  file VARCHAR(500) NULL,
  line INT NULL,
  stack_trace MEDIUMTEXT NULL,
  fingerprint CHAR(64) NULL,
  metadata JSON NULL,
  PRIMARY KEY(error_guid), KEY ix_error_time(occurred_at), KEY ix_error_fp(fingerprint), KEY ix_error_request(request_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS outbox_events (
  outbox_event_guid BINARY(16) NOT NULL,
  tenant_guid BINARY(16) NULL,
  event_type VARCHAR(128) NOT NULL,
  aggregate_type VARCHAR(96) NULL,
  aggregate_guid BINARY(16) NULL,
  payload JSON NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
  available_at DATETIME(6) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  last_error VARCHAR(1000) NULL,
  created_at DATETIME(6) NOT NULL,
  processed_at DATETIME(6) NULL,
  PRIMARY KEY(outbox_event_guid), KEY ix_outbox_ready(status,available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
  job_guid BINARY(16) NOT NULL,
  queue_name VARCHAR(64) NOT NULL,
  job_type VARCHAR(128) NOT NULL,
  payload JSON NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'QUEUED',
  attempts INT NOT NULL DEFAULT 0,
  available_at DATETIME(6) NOT NULL,
  locked_at DATETIME(6) NULL,
  locked_by VARCHAR(128) NULL,
  last_error VARCHAR(1000) NULL,
  created_at DATETIME(6) NOT NULL,
  completed_at DATETIME(6) NULL,
  PRIMARY KEY(job_guid), KEY ix_jobs_ready(queue_name,status,available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS impersonation_grants (
  impersonation_guid BINARY(16) NOT NULL,
  admin_guid BINARY(16) NOT NULL,
  organisation_guid BINARY(16) NOT NULL,
  user_guid BINARY(16) NOT NULL,
  token_hash BINARY(32) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  expires_at DATETIME(6) NOT NULL,
  used_at DATETIME(6) NULL,
  revoked_at DATETIME(6) NULL,
  PRIMARY KEY(impersonation_guid),
  UNIQUE KEY uk_imp_token(token_hash),
  KEY ix_imp_org(organisation_guid,expires_at),
  CONSTRAINT fk_imp_admin FOREIGN KEY(admin_guid) REFERENCES platform_admins(admin_guid),
  CONSTRAINT fk_imp_org FOREIGN KEY(organisation_guid) REFERENCES organisations(organisation_guid),
  CONSTRAINT fk_imp_user FOREIGN KEY(user_guid) REFERENCES platform_users(user_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_heartbeats (
  heartbeat_guid BINARY(16) NOT NULL,
  application_guid BINARY(16) NOT NULL,
  environment VARCHAR(32) NOT NULL,
  version VARCHAR(32) NULL,
  status VARCHAR(32) NOT NULL,
  metrics JSON NULL,
  received_at DATETIME(6) NOT NULL,
  PRIMARY KEY(heartbeat_guid), KEY ix_hb_app(application_guid,received_at),
  CONSTRAINT fk_hb_app FOREIGN KEY(application_guid) REFERENCES applications(application_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  bucket_key BINARY(32) NOT NULL,
  window_start DATETIME(6) NOT NULL,
  hit_count INT NOT NULL DEFAULT 0,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(bucket_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_settings (
  setting_key VARCHAR(128) NOT NULL,
  setting_value JSON NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY(setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
