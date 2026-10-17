<?php
return [
    'app' => [
        'name' => 'Digiti Platform',
        'env' => 'production',
        'url' => 'https://platform.digiti.in',
        'auth_url' => 'https://auth.digiti.in',
        'domains_target' => 'domains.digiti.in',
        'key' => 'CHANGE_ME_64_HEX',
        'update_key' => 'CHANGE_ME',
        'timezone' => 'Asia/Kolkata',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'digiti_platform',
        'user' => '',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'redis' => [
        'enabled' => true,
        'host' => '127.0.0.1',
        'port' => 6379,
        'username' => null,
        'password' => null,
        'database' => 0,
        'prefix' => 'digiti:prod:platform:',
    ],
    'mail' => [
        'from' => 'no-reply@digiti.in',
    ],
    'billing' => [
        'default_provider' => 'manual',
        'razorpay_key_id' => '',
        'razorpay_key_secret' => '',
        'stripe_secret' => '',
        'stripe_webhook_secret' => '',
    ],
    'integrations' => [
        // Exact trusted URL: HTTPS, no query/redirect; configure actual app host privately.
        'epaper_invalidation_url' => '',
        // Same 32+ char secret as ePaper platform.invalidation_secret; keep out of Git.
        'epaper_invalidation_secret' => '',
    ],
    'edge' => [
        'provider' => 'manual',
        'cloudflare_api_token' => '',
        'cloudflare_zone_id' => '',
    ],
];
