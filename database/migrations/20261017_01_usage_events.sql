CREATE TABLE IF NOT EXISTS usage_events (
  event_guid BINARY(16) NOT NULL,
  product_tenant_guid BINARY(16) NOT NULL,
  feature_guid BINARY(16) NOT NULL,
  period_key VARCHAR(7) NOT NULL,
  delta DECIMAL(20,4) NOT NULL,
  payload_hash BINARY(32) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY(event_guid),
  KEY ix_usage_event_tenant_period(product_tenant_guid,period_key),
  CONSTRAINT fk_usage_event_tenant FOREIGN KEY(product_tenant_guid) REFERENCES product_tenants(product_tenant_guid),
  CONSTRAINT fk_usage_event_feature FOREIGN KEY(feature_guid) REFERENCES features(feature_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
