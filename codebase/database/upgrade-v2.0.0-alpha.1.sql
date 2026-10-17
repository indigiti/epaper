-- EP-DI v2.0.0-alpha.1 performance index upgrade
-- Apply once to an existing v1.1.9.11 database before production performance testing.
-- New installations already receive this index from database/schema.sql.

ALTER TABLE ep_settings
  ADD INDEX ix_setting_scope (tenant_guid, scope_type, scope_guid, setting_key);
