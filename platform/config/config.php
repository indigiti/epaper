<?php
return array (
  'app' => 
  array (
    'name' => 'Digiti Platform',
    'env' => 'production',
    'url' => 'https://platform.digiti.in',
    'auth_url' => 'https://auth.digiti.in',
    'domains_target' => 'domains.digiti.in',
    'key' => 'a47dfb65b2830aa8b99904672d28b38825091b7e5fd6462b2aa0fcbb757a9650',
    'update_key' => '6f82568927624c4bd25bf9a8b1d146d27608b0fc223f0e95',
    'timezone' => 'Asia/Kolkata',
  ),
  'db' => 
  array (
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'emenjwayxb',
    'user' => 'emenjwayxb',
    'pass' => '3Mae5YNhXD',
    'charset' => 'utf8mb4',
  ),
  'redis' => 
  array (
    'enabled' => true,
    'host' => '127.0.0.1',
    'port' => 6379,
    'username' => 'emenjwayxb',
    'password' => 'HcAs7A6han',
    'database' => 0,
    'prefix' => 'digiti:prod:platform:',
  ),
  'mail' => 
  array (
    'from' => 'no-reply@digiti.in',
  ),
  'billing' => 
  array (
    'default_provider' => 'manual',
    'razorpay_key_id' => '',
    'razorpay_key_secret' => '',
    'stripe_secret' => '',
    'stripe_webhook_secret' => '',
  ),
  'edge' => 
  array (
    'provider' => 'manual',
    'cloudflare_api_token' => '',
    'cloudflare_zone_id' => '',
  ),
);
