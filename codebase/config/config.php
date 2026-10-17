<?php
return array (
  'app' => 
  array (
    'name' => 'ePaper SaaS',
    'environment' => 'production',
    'url' => 'https://epaper.digiti.in',
    'timezone' => 'Asia/Kolkata',
    'key' => '1d7111da69835d3521258436bc65e1cdcff37e0ff9b308600933a65c20cdc6eb',
  ),
  'db' => 
  array (
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'hvdqsngejr',
    'user' => 'hvdqsngejr',
    'pass' => 'x8AHUR4TFh',
    'charset' => 'utf8mb4',
  ),
  'redis' => 
  array (
    'enabled' => true,
    'host' => '127.0.0.1',
    'port' => 6379,
    'username' => 'hvdqsngejr',
    'password' => 'kdXkX8SDf9',
    'database' => 0,
    'prefix' => 'hvdqsngejr:',
  ),
  'platform' => 
  array (
    'url' => 'https://platform.digiti.in',
    'product_key' => 'EPAPER',
    'application_key' => 'EPAPER',
    'application_guid' => '01a05e03-9afc-77db-8a5e-b35b58af5b7d',
    'oauth_client_id' => 'epaper_026383db1829e179be8d',
    'service_token' => 'dgt_UpV4oK7RH9dTm9pC_K4zVXfzBY7sx2y7Oz-KYvxDn8d9YAiW',
    'default_tenant_guid' => '',
    'default_organisation_guid' => '',
  ),
  'auth' => 
  array (
    'admin_roles' => 
    array (
      0 => 'OWNER',
      1 => 'ADMIN',
      2 => 'PUBLISHER_ADMIN',
      3 => 'EDITOR',
      4 => 'OPERATOR',
    ),
  ),
  'processing' => 
  array (
    'max_upload_mb' => 300,
    'tile_size' => 512,
    'ocr_languages' => 'eng+hin+mar',
  ),
);
