<?php

return [
    'providers' => [
        'amazon' => App\Marketplaces\Amazon\AmazonReadProvider::class,
        'flipkart' => App\Marketplaces\Flipkart\FlipkartReadProvider::class,
    ],
    'max_pages_per_run' => (int) env('MARKETPLACE_SYNC_MAX_PAGES', 100),
    'lock_seconds' => (int) env('MARKETPLACE_SYNC_LOCK_SECONDS', 900),
    'reconciliation_max_snapshot_age_hours' => (int) env('MARKETPLACE_RECONCILIATION_MAX_SNAPSHOT_AGE_HOURS', 24),
    'inventory_automation' => [
        'enabled' => (bool) env('MARKETPLACE_INVENTORY_AUTOMATION_ENABLED', true),
        'default_interval_hours' => (int) env('MARKETPLACE_INVENTORY_AUTOMATION_INTERVAL_HOURS', 6),
        'default_low_stock_threshold' => (int) env('MARKETPLACE_INVENTORY_LOW_STOCK_THRESHOLD', 5),
    ],
];
