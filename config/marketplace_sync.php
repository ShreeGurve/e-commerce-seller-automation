<?php

return [
    'providers' => [
        'amazon' => App\Marketplaces\Amazon\AmazonReadProvider::class,
        'flipkart' => App\Marketplaces\Flipkart\FlipkartReadProvider::class,
    ],
    'max_pages_per_run' => (int) env('MARKETPLACE_SYNC_MAX_PAGES', 100),
    'lock_seconds' => (int) env('MARKETPLACE_SYNC_LOCK_SECONDS', 900),
];
