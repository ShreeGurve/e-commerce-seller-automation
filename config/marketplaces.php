<?php

return [
    'push_enabled' => (bool) env('MARKETPLACE_PUSH_ENABLED', false),
    'inventory_auto_sync' => (bool) env('MARKETPLACE_INVENTORY_AUTO_SYNC', false),
    'price_push_requires_approval' => true,
    'amazon' => [
        'enabled' => (bool) env('AMAZON_SP_API_ENABLED', false),
        'api_calls_enabled' => (bool) env('AMAZON_SP_API_CALLS_ENABLED', false),
        'endpoint' => env('AMAZON_SP_API_ENDPOINT'),
        'region' => env('AMAZON_SP_API_REGION', 'eu'),
        'marketplace_id' => env('AMAZON_MARKETPLACE_ID'),
        'client_id' => env('AMAZON_LWA_CLIENT_ID'),
        'client_secret' => env('AMAZON_LWA_CLIENT_SECRET'),
        'refresh_token' => env('AMAZON_LWA_REFRESH_TOKEN'),
        'aws_access_key_id' => env('AMAZON_AWS_ACCESS_KEY_ID'),
        'aws_secret_access_key' => env('AMAZON_AWS_SECRET_ACCESS_KEY'),
        'role_arn' => env('AMAZON_SP_API_ROLE_ARN'),
    ],
    'flipkart' => [
        'enabled' => (bool) env('FLIPKART_SELLER_API_ENABLED', false),
        'api_calls_enabled' => (bool) env('FLIPKART_SELLER_API_CALLS_ENABLED', false),
        'base_url' => env('FLIPKART_SELLER_API_BASE_URL'),
        'seller_id' => env('FLIPKART_SELLER_ID'),
        'access_token' => env('FLIPKART_SELLER_API_TOKEN'),
    ],
];
